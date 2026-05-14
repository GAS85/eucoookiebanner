<?php
/**
 * EU Cookie Banner Module for PrestaShop v9
 *
 * Shows a full-page cookie consent overlay.
 * Admin can configure the banner text.
 * Users can only Agree (accept) or Leave (redirected away).
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class eucoookiebanner extends Module
{
    const CONFIG_TEXT    = 'EUCOOKIEBANNER_TEXT';
    const CONFIG_LEAVE   = 'EUCOOKIEBANNER_LEAVE_URL';
    const COOKIE_NAME    = 'eucookie_accepted';
    const COOKIE_EXPIRE  = 7; // days

    public function __construct()
    {
        $this->name             = 'eucoookiebanner';
        $this->tab              = 'front_office_features';
        $this->version          = '0.0.1';
        $this->author           = 'Georgiy Sitnikov';
        $this->need_instance    = 0;
        $this->bootstrap        = true;

        $this->ps_versions_compliancy = [
            'min' => '8.0.0',
            'max' => _PS_VERSION_,
        ];

        parent::__construct();

        $this->displayName = $this->l('EU Cookie Banner');
        $this->description = $this->l(
            'Displays a GDPR/EU cookie consent overlay. Visitors must agree to continue or leave the shop.'
        );
    }

    /* ------------------------------------------------------------------ */
    /*  Install / Uninstall                                                 */
    /* ------------------------------------------------------------------ */

    public function install(): bool
    {
        $defaultText = 'This website uses cookies to ensure you get the best browsing experience. '
            . 'By clicking "I Agree", you consent to our use of cookies in accordance with the EU Cookie Law.';

        return parent::install()
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayBeforeBodyClosingTag')
            && Configuration::updateValue(self::CONFIG_TEXT, $defaultText)
            && Configuration::updateValue(self::CONFIG_LEAVE, 'https://duckduckgo.com/');
    }

    public function uninstall(): bool
    {
        Configuration::deleteByName(self::CONFIG_TEXT);
        Configuration::deleteByName(self::CONFIG_LEAVE);

        return parent::uninstall();
    }

    /* ------------------------------------------------------------------ */
    /*  Back-office configuration page                                      */
    /* ------------------------------------------------------------------ */

    public function getContent(): string
    {
        $output = '';

        if (Tools::isSubmit('submitEuCookieBanner')) {
            $bannerText = trim(Tools::getValue(self::CONFIG_TEXT));
            $leaveUrl   = trim(Tools::getValue(self::CONFIG_LEAVE));

            $errors = [];

            if ($bannerText === '') {
                $errors[] = $this->l('Cookie banner text cannot be empty.');
            }

            if ($leaveUrl !== '' && !Validate::isUrl($leaveUrl)) {
                $errors[] = $this->l('"Leave" redirect URL is not a valid URL.');
            }

            if (count($errors) > 0) {
                foreach ($errors as $err) {
                    $output .= $this->displayError($err);
                }
            } else {
                Configuration::updateValue(self::CONFIG_TEXT, $bannerText);
                Configuration::updateValue(self::CONFIG_LEAVE, $leaveUrl ?: 'https://duckduckgo.com/');
                $output .= $this->displayConfirmation($this->l('Settings saved successfully.'));
            }
        }

        return $output . $this->renderConfigForm();
    }

    protected function renderConfigForm(): string
    {
        $helper = new HelperForm();

        $helper->show_toolbar    = false;
        $helper->table           = $this->table;
        $helper->module          = $this;
        $helper->default_form_language  = (int) $this->context->language->id;
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->identifier      = $this->identifier;
        $helper->submit_action   = 'submitEuCookieBanner';
        $helper->currentIndex    = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name
            . '&tab_module=' . $this->tab
            . '&module_name=' . $this->name;
        $helper->token           = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars        = [
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
                    . 'Visitors must click "I Agree" to continue browsing, or they will be redirected to the "Leave URL".'
                ),
                'input' => [
                    [
                        'type'     => 'textarea',
                        'label'    => $this->l('Banner Text'),
                        'name'     => self::CONFIG_TEXT,
                        'required' => true,
                        'rows'     => 6,
                        'cols'     => 60,
                        'desc'     => $this->l('Text displayed inside the cookie consent banner. HTML is allowed.'),
                    ],
                    [
                        'type'    => 'text',
                        'label'   => $this->l('"Leave Shop" Redirect URL'),
                        'name'    => self::CONFIG_LEAVE,
                        'required'=> false,
                        'size'    => 80,
                        'desc'    => $this->l(
                            'URL visitors are redirected to when they close the banner without agreeing '
                            . '(default: https://www.duckduckgo.com).'
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

    protected function getConfigFieldsValues(): array
    {
        return [
            self::CONFIG_TEXT  => Configuration::get(self::CONFIG_TEXT),
            self::CONFIG_LEAVE => Configuration::get(self::CONFIG_LEAVE),
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Front-office hooks                                                  */
    /* ------------------------------------------------------------------ */

    /**
     * Inject CSS + JS into <head>.
     */
    public function hookDisplayHeader(array $params): void
    {
        // Skip if the visitor has already accepted
        if ($this->isAlreadyAccepted()) {
            return;
        }

        $this->context->controller->addCSS($this->_path . 'views/css/cookie_banner.css');
        $this->context->controller->addJS($this->_path  . 'views/js/cookie_banner.js');
    }

    /**
     * Render the banner HTML just before </body>.
     */
    public function hookDisplayBeforeBodyClosingTag(array $params): string
    {
        if ($this->isAlreadyAccepted()) {
            return '';
        }

        $leaveUrl   = Configuration::get(self::CONFIG_LEAVE) ?: 'https://www.duckduckgo.com';
        $bannerText = Configuration::get(self::CONFIG_TEXT);

        $this->context->smarty->assign([
            'eucb_text'      => $bannerText,
            'eucb_leave_url' => $leaveUrl,
        ]);

        return $this->display(__FILE__, 'views/templates/hook/cookie_banner.tpl');
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Returns true when the visitor has already set the acceptance cookie
     * OR when an AJAX request marks acceptance.
     */
    private function isAlreadyAccepted(): bool
    {
        // Check native PS cookie
        if ($this->context->cookie->__isset(self::COOKIE_NAME)) {
            return (bool) $this->context->cookie->__get(self::COOKIE_NAME);
        }

        return false;
    }
}
