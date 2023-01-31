<?php
/**
* 2007-2023 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2023 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

use DSMoreDetailObject;

if (!defined('_PS_VERSION_')) {
    exit;
}

require 'vendor/autoload.php';

class Dsmoredetail extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'dsmoredetail';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Dark-Side.pro';
        $this->need_instance = 1;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('DS: More detail');
        $this->description = $this->l('This module add popup with custom text on selected product pages');

        $this->confirmUninstall = $this->l('');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        Configuration::updateValue('DSMOREDETAIL_LIVE_MODE', false);

        include(dirname(__FILE__).'/sql/install.php');
        $this->genereteFreshData();

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('displayBackOfficeHeader') &&
            $this->registerHook('actionProductSave') &&
            $this->registerHook('actionProductUpdate') &&
            $this->registerHook('actionProductDelete') &&
            $this->registerHook('displayAdminProductsExtra') &&
            $this->registerHook('displayProductExtraContent');
    }

    protected function genereteFreshData()
    {
        $db = \Db::getInstance();
        $sql = 'INSERT INTO ' . _DB_PREFIX_ . 'dsmoredetail (id_product, status) SELECT id_product, 0 FROM ' . _DB_PREFIX_ . 'product';
        $result = $db->execute($sql);
    }

    protected function deleteAllData()
    {
        $db = \Db::getInstance();
        $sql = 'DELETE FROM '._DB_PREFIX_.'dsmoredetail';
        $result = $db->execute($sql);
    }

    public function uninstall()
    {
        Configuration::deleteByName('DSMOREDETAIL_LIVE_MODE');
        $this->deleteAllData();

        include(dirname(__FILE__).'/sql/uninstall.php');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitDsmoredetailModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitDsmoredetailModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Live mode'),
                        'name' => 'DSMOREDETAIL_LIVE_MODE',
                        'is_bool' => true,
                        'desc' => $this->l('Use this module in live mode'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                       
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => $this->l('Info message'),
                        'desc' => $this->l('Write message to your customers'),
                        'name' => 'DSMOREDETAIL_MESSAGE',
                        'lang' => true,
                        'cols' => 60,
                        'rows' => 10,
                        'class' => 'rte',
                        'autoload_rte' => true,
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'DSMOREDETAIL_LIVE_MODE' => Configuration::get('DSMOREDETAIL_LIVE_MODE', true),
            'DSMOREDETAIL_MESSAGE' => Configuration::get('DSMOREDETAIL_MESSAGE'),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('configure') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    public function hookActionProductSave($params)
    {
        $id_product = $params['id_product'];
        $status = (int) Tools::getValue('dsmdStatus');
        $id = $this->getDSMDByIdProduct($id_product);

        if ($id == false) {
            $this->createMoreDetail($id_product, $status);
        } else {
            $this->updateData($id_product, $status);
        }
    }

    protected function getDSMDByIdProduct(int $id_product)
    {
        $sql = new DbQuery;
        $sql->select('id')
            ->from('dsmoredetail')
            ->where('id_product ='.$id_product);

        $result = Db::getInstance()->executeS($sql); 
        
        if (!empty($result)) {
            return (int) $result[0]['id'];
        }

        return false;
    }

    protected function updateData(int $id_product, int $status): int
    {
        $sql = new DbQuery;
        $sql->select('id')
            ->from('dsmoredetail')
            ->where('id_product ='.$id_product)
            ->limit(1);

        $result = Db::getInstance()->executeS($sql); 
        $id = $result[0]['id'];

        $dspopularproduct = new DSMoreDetailObject($id);
        $dspopularproduct->status = $status;
        $dspopularproduct->update();

        return $dspopularproduct->id;
    }

    protected function createMoreDetail(int $id_product, int $status): int
    {
        $dspopularproduct = new DSMoreDetailObject();
        $dspopularproduct->id_product = $id_product;
        $dspopularproduct->status = $status;
        $dspopularproduct->add();

        return $dspopularproduct->id;
    }

    public function hookActionProductUpdate($params)
    {
        $id_product = $params['id_product'];
        $status = (int) Tools::getValue('dsmdStatus');

        $this->updateData($id_product, $status);
    }

    public function hookActionProductDelete($params)
    {
        $id_product = $params['id_product'];

        $this->deleteData($id_product);
    }

    protected function deleteData(int $id): void
    {
        $dspopularproduct = new DSMoreDetailObject($id);
        $dspopularproduct->delete();

    }

    public function hookDisplayAdminProductsExtra($params)
    {
        $id_product = $params['id_product'];
        $dspopularproductId = $this->getDSMDByIdProduct($id_product);
        $dspopularproduct = $this->getDSMoreDetail($dspopularproductId);
        $status = $dspopularproduct->status;

        $this->context->smarty->assign('status', $status);

        return $this->context->smarty->fetch($this->local_path.'views/templates/admin/displayAdminProductsExtra.tpl');
    }

    protected function getDSMoreDetail(int $id): DSMoreDetailObject
    {
        return new DSMoreDetailObject($id);
    }

    public function hookDisplayProductExtraContent($params)
    {
        $live = Configuration::get('DSMOREDETAIL_LIVE_MODE');
        $message = Configuration::get('DSMOREDETAIL_MESSAGE');
        $id_product = Tools::getValue('id_product');

        $dsmdID = $this->getDSMDByIdProduct($id_product);

        if ($dsmdID !== false) {
            $dsmd = $this->getDSMoreDetail($dsmdID);
            
            if ($dsmd->status == true) {
                $this->context->smarty->assign('message', $message);
           
                return $this->context->smarty->fetch($this->local_path.'views/templates/hook/displayProductExtraContent.tpl');
            }
        }
    }
}
