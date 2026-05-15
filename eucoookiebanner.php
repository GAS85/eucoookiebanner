<?php
/**
 * EU Cookie Banner Module for PrestaShop v9
 *
 * Shows a full-page cookie consent overlay.
 * Admin can configure the banner text per shop language (TinyMCE editor).
 * HTML is stored and rendered correctly.
 * Users can only Agree (accept) or Leave (redirected away).
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Eucoookiebanner extends Module
{
    const CONFIG_TEXT              = 'EUCOOKIEBANNER_TEXT';
    const CONFIG_LEAVE             = 'EUCOOKIEBANNER_LEAVE_URL';
    const COOKIE_NAME              = 'eucookie_accepted';
    const COOKIE_EXPIRE            = 7; // days
    const UPDATE_JSON_URL          = 'https://git.sitnikov.eu/gas/eucoookiebanner/raw/branch/main/latest.json';
    const CONFIG_LAST_UPDATE_CHECK = 'EUCOOKIEBANNER_LAST_UPDATE_CHECK';
    const CONFIG_UPDATE_CACHE      = 'EUCOOKIEBANNER_UPDATE_CACHE';

    public function __construct()
    {
        $this->name          = 'eucoookiebanner';
        $this->tab           = 'front_office_features';
        $this->version       = '0.3.3';
        $this->author        = 'Georgiy Sitnikov';
        $this->need_instance = 0;
        $this->bootstrap     = true;

        $this->ps_versions_compliancy = [
            'min' => '9.0.0',
            'max' => _PS_VERSION_,
        ];

        parent::__construct();

        $this->displayName = $this->l('EU Cookie Banner');
        $this->description = sprintf(
            $this->l(
                'Displays a GDPR/EU cookie consent overlay. Visitors must agree to continue or leave the shop. '
                . 'Documentation & source: https://git.sitnikov.eu/gas/eucoookiebanner '
                . '| Author: %s | Version: %s '
                . '| Support via PayPal: https://www.paypal.com/paypalme/GeorgiySitnikov'
            ),
            $this->author,
            $this->version
        );
    }

    /* ------------------------------------------------------------------ */
    /*  Install / Uninstall                                                 */
    /* ------------------------------------------------------------------ */

    public function install(): bool
    {
        $defaultText = 'This website uses cookies to ensure you get the best browsing experience. '
            . 'By clicking "<strong>I Agree</strong>", you consent to our use of cookies '
            . 'in accordance with the EU Cookie Law.';

        // Build per-language default values
        $langValues = [];
        foreach (Language::getLanguages(false) as $lang) {
            $langValues[(int) $lang['id_lang']] = $defaultText;
        }

        return parent::install()
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayBeforeBodyClosingTag')
            // third arg = true → allow HTML, skip pSQL tag-escaping
            && Configuration::updateValue(self::CONFIG_TEXT, $langValues, true)
            && Configuration::updateValue(self::CONFIG_LEAVE, 'https://duckduckgo.com/');
    }

    public function uninstall(): bool
    {
        Configuration::deleteByName(self::CONFIG_TEXT);
        Configuration::deleteByName(self::CONFIG_LEAVE);
        Configuration::deleteByName(self::CONFIG_UPDATE_CACHE);
        Configuration::deleteByName(self::CONFIG_LAST_UPDATE_CHECK);

        return parent::uninstall();
    }

    /* ------------------------------------------------------------------ */
    /*  Back-office configuration page                                      */
    /* ------------------------------------------------------------------ */

    public function getContent(): string
    {
        $output = '';

        // Add TinyMCE initialization with proper event handlers
        $this->context->controller->addJS($this->_path . 'views/js/admin_tinymce.js');

        $output .= $this->renderUpdateInfo();

        if (Tools::isSubmit('submitEuCookieBanner')) {
            $errors = [];

            // ── Collect multilingual banner texts ──────────────────────
            $bannerTexts   = [];
            $hasAtLeastOne = false;

            foreach (Language::getLanguages(false) as $lang) {
                $idLang               = (int) $lang['id_lang'];
                $val                  = (string) Tools::getValue(self::CONFIG_TEXT . '_' . $idLang, '');
                $bannerTexts[$idLang] = $val;

                if (trim(strip_tags($val)) !== '') {
                    $hasAtLeastOne = true;
                }
            }

            if (!$hasAtLeastOne) {
                $errors[] = $this->l('Cookie banner text cannot be empty for at least one language.');
            }

            // ── Validate leave URL ─────────────────────────────────────
            $leaveUrl = trim(Tools::getValue(self::CONFIG_LEAVE));

            if ($leaveUrl !== '' && !Validate::isUrl($leaveUrl)) {
                $errors[] = $this->l('"Leave" redirect URL is not a valid URL.');
            }

            if (count($errors) > 0) {
                foreach ($errors as $err) {
                    $output .= $this->displayError($err);
                }
            } else {
                // true = html allowed → tags are NOT escaped before DB storage
                Configuration::updateValue(self::CONFIG_TEXT, $bannerTexts, true);
                Configuration::updateValue(self::CONFIG_LEAVE, $leaveUrl ?: 'https://duckduckgo.com/');
                $output .= $this->displayConfirmation($this->l('Settings saved successfully.'));
            }
        }

        return $output . $this->renderConfigForm();
    }

    protected function renderConfigForm(): string
    {
        $helper = new HelperForm();

        $helper->show_toolbar             = false;
        $helper->table                    = $this->table;
        $helper->module                   = $this;
        $helper->default_form_language    = (int) $this->context->language->id;
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->identifier               = $this->identifier;
        $helper->submit_action            = 'submitEuCookieBanner';
        $helper->currentIndex             = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name
            . '&tab_module=' . $this->tab
            . '&module_name=' . $this->name;
        $helper->token    = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFieldsValues(),
            'languages'    => $this->context->controller->getLanguages(),
            'id_language'  => (int) $this->context->language->id,
        ];

        return $helper->generateForm([$this->getConfigFormDefinition()]);
    }

    protected function getConfigFormDefinition(): array
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('EU Cookie Banner Settings'),
                    'icon'  => 'icon-cogs',
                ],
                'description' => $this->l(
                    'Configure the cookie consent overlay shown to every new visitor. '
                    . 'Visitors must click "I Agree" to continue browsing, or they will be redirected to the "Leave URL". '
                    . 'HTML is fully supported – use the editor to add links, bold text, etc.'
                ),
                'input' => [
                    [
                        // lang: true -> one tab per shop language
                        'type'     => 'textarea',
                        'label'    => $this->l('Banner Text'),
                        'name'     => self::CONFIG_TEXT,
                        'lang'     => true,
                        'autoload_rte' => true,
                        'required' => true,
                        'class'    => 'rte', // Add class to identify RTE fields
                        'rows'     => 8,
                        'cols'     => 60,
                        'desc'     => $this->l(
                            'HTML is fully supported (links, bold, italic, …). '
                            . 'Switch tabs to set a different text per shop language.'
                        ),
                    ],
                    [
                        'type'     => 'text',
                        'label'    => $this->l('"Leave Shop" Redirect URL'),
                        'name'     => self::CONFIG_LEAVE,
                        'required' => false,
                        'size'     => 80,
                        'desc'     => $this->l(
                            'Visitors are redirected here when they dismiss the banner without agreeing '
                            . '(default: https://duckduckgo.com/).'
                        ),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right',
                ],
            ],
        ];
    }

    /**
     * Returns current saved values for every field.
     * Multilingual fields MUST be an array keyed by id_lang.
     */
    protected function getConfigFieldsValues(): array
    {
        $values = [
            self::CONFIG_LEAVE => Configuration::get(self::CONFIG_LEAVE),
        ];

        foreach (Language::getLanguages(false) as $lang) {
            $idLang = (int) $lang['id_lang'];
            $values[self::CONFIG_TEXT][$idLang] = Configuration::get(
                self::CONFIG_TEXT,
                $idLang,   // Fixed: id_lang as 2nd argument
                null,      // id_shop_group (null = current)
                null       // id_shop (null = current)
            );
        }

        return $values;
    }

    /* ------------------------------------------------------------------ */
    /*  Front-office hooks                                                  */
    /* ------------------------------------------------------------------ */

    public function hookDisplayHeader(array $params): void
    {
        if ($this->isAlreadyAccepted()) {
            return;
        }

        $this->context->controller->addCSS($this->_path . 'views/css/cookie_banner.css');
        $this->context->controller->addJS($this->_path . 'views/js/cookie_banner.js');
    }

    public function hookDisplayBeforeBodyClosingTag(array $params): string
    {
        if ($this->isAlreadyAccepted()) {
            return '';
        }

        $leaveUrl = Configuration::get(self::CONFIG_LEAVE) ?: 'https://duckduckgo.com/';

        // Retrieve text for the active front-office language
        $idLang     = (int) $this->context->language->id;
        $bannerText = Configuration::get(self::CONFIG_TEXT, $idLang, null, null);

        // Fallback → default shop language
        if (empty($bannerText)) {
            $idLang     = (int) Configuration::get('PS_LANG_DEFAULT');
            $bannerText = Configuration::get(self::CONFIG_TEXT, $idLang, null, null);
        }

        $this->context->smarty->assign([
            'eucb_text'      => $bannerText,  // output via {nofilter} in TPL → HTML rendered
            'eucb_leave_url' => $leaveUrl,
        ]);

        return $this->display(__FILE__, 'views/templates/hook/cookie_banner.tpl');
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                             */
    /* ------------------------------------------------------------------ */

    private function isAlreadyAccepted(): bool
    {
        if ($this->context->cookie->__isset(self::COOKIE_NAME)) {
            return (bool) $this->context->cookie->__get(self::COOKIE_NAME);
        }

        return false;
    }

    /* ------------------------------------------------------------------ */
    /*  Update checker (24 h cache)                                         */
    /* ------------------------------------------------------------------ */

    private function renderUpdateInfo(): string
    {
        $remote = $this->getRemoteVersionData();

        if (!$remote || empty($remote['version'])) {
            return $this->displayWarning($this->l('Unable to check for updates.'));
        }

        $remoteVersion = trim($remote['version']);

        if (version_compare($remoteVersion, $this->version, '>')) {
            $downloadLink = '';

            if (!empty($remote['download_url'])) {
                $downloadLink = sprintf(
                    '<p><a href="%s" target="_blank" class="btn btn-primary">%s</a></p>',
                    htmlspecialchars($remote['download_url'], ENT_QUOTES, 'UTF-8'),
                    $this->l('Download latest version')
                );
            }

            return $this->displayWarning(
                sprintf(
                    $this->l('A new version (%s) is available. Current version: %s'),
                    $remoteVersion,
                    $this->version
                ) . $downloadLink
            );
        }

        return $this->displayConfirmation($this->l('You are using the latest version.'));
    }

    private function getRemoteVersionData(): ?array
    {
        $cache     = Configuration::get(self::CONFIG_UPDATE_CACHE);
        $lastCheck = (int) Configuration::get(self::CONFIG_LAST_UPDATE_CHECK);

        if ($cache && $lastCheck > (time() - 86400)) {
            $decoded = json_decode($cache, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        try {
            $json = Tools::file_get_contents(self::UPDATE_JSON_URL);

            if (!$json) {
                return null;
            }

            $data = json_decode($json, true);

            if (!is_array($data)) {
                return null;
            }

            Configuration::updateValue(self::CONFIG_UPDATE_CACHE, json_encode($data));
            Configuration::updateValue(self::CONFIG_LAST_UPDATE_CHECK, time());

            return $data;
        } catch (Exception $e) {
            return null;
        }
    }
}
