<?php
/**
* 2007-2019 PrestaShop
*
*  @author    Farmalisto <alejandro.villegas@farmalisto.com.co>
*  @copyright 2007-2019 Farmalisto
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!defined('_PS_VERSION_')) {
    exit;
}

const UPDATEINVENTORY_PATH_LOG = _PS_ROOT_DIR_ . "/modules/updateInventory/log/";

class UpdateInventory extends Module
{
    protected $config_form = false;
    private $UPDATEINVENTORY_LIVE_MODE;
    private $UPDATEINVENTORY_ACCOUNT_PASSWORD;

    public function __construct()
    {
        $this->name = 'updateInventory';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Farmalisto';
        $this->need_instance = 1;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Update Inventory');
        $this->description = $this->l('Module for update inventory from csv file');

        $this->confirmUninstall = $this->l('Are you sure want to unistall this module?');

        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);

        $this->UPDATEINVENTORY_LIVE_MODE = Configuration::get('UPDATEINVENTORY_LIVE_MODE');
        $this->UPDATEINVENTORY_ACCOUNT_PASSWORD = Configuration::get('UPDATEINVENTORY_ACCOUNT_PASSWORD');

        if ($this->active && Configuration::get('updateInventory') == '') {
            $this->warning = $this->l('You have to configure your module');
           }
         
           $this->errors = array();
           if ($this->UPDATEINVENTORY_LIVE_MODE == 1) {
               $this->updateInv();
           }
        //    ?k=Andres.12
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        Configuration::updateValue('UPDATEINVENTORY_LIVE_MODE', false);
        Configuration::updateValue('UPDATEINVENTORY_ACCOUNT_PASSWORD', '');

        include(dirname(__FILE__).'/sql/install.php');

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader') &&
            $this->registerHook('displayHome');
    }

    public function uninstall()
    {
        Configuration::deleteByName('UPDATEINVENTORY_LIVE_MODE');
        Configuration::deleteByName('UPDATEINVENTORY_ACCOUNT_PASSWORD', '');

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
        if (((bool)Tools::isSubmit('submitUpdateInventoryModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output.$this->renderForm();
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
        $helper->submit_action = 'submitUpdateInventoryModule';
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
                        'name' => 'UPDATEINVENTORY_LIVE_MODE',
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
                        'col' => 2,
                        'type' => 'text',
                        'name' => 'UPDATEINVENTORY_ACCOUNT_PASSWORD',
                        'label' => $this->l('Password'),
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
            'UPDATEINVENTORY_LIVE_MODE' => Configuration::get('UPDATEINVENTORY_LIVE_MODE', true),
            'UPDATEINVENTORY_ACCOUNT_PASSWORD' => Configuration::get('UPDATEINVENTORY_ACCOUNT_PASSWORD', null),
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
  * Error log
  *
  * @param string $text text that will be saved in the file
  * @return void Error record in file "log_errors.log"
  */
    public static function logtxt($text = "")
    {

    if (file_exists(UPDATEINVENTORY_PATH_LOG)) {
    $fp = fopen(_PS_ROOT_DIR_ . "/modules/updateInventory/log/log_errors.log", "a+");
    fwrite($fp, date('l jS \of F Y h:i:s A') . ", " . $text . "\r\n");
    fclose($fp);
    return true;
    } else {
    self::createPath(UPDATEINVENTORY_PATH_LOG);
    }
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
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

    public function hookDisplayHome()
    {
        /* Place your code here. */
    }

    /**
     * this function is for update inventory from csv file
     */
    public function updateInv()
    {
        // self::logtxt("probando log...");
        // https://devdocs.prestashop.com/1.7/modules/concepts/controllers/front-controllers/
        if ($this->UPDATEINVENTORY_LIVE_MODE == 1) {
            $key = $this->UPDATEINVENTORY_ACCOUNT_PASSWORD;
            if(Tools::getValue('k') == $key){ 
            // var_dump('funcionando!');

            clearstatcache(); 
            $registros = array();
            // obtenemos la fecha actual de consumo
            $date = date('Y-m-d');
            $today = str_replace("-","",$date);
            $nombre_fichero = $_SERVER['DOCUMENT_ROOT'].'/OM/uploads/INVENTARIO_ABBOTT_E-COMMERCE_'.$today.'.txt';
            clearstatcache();
            if (file_exists($nombre_fichero)) {
                if (($fichero = fopen(_PS_BASE_URL_."/OM/uploads/INVENTARIO_ABBOTT_E-COMMERCE_".$today.".txt", "r")) !== false) {
                    // Lee los nombres de los campos
                    $nombres_campos = fgetcsv($fichero, 0, "|", "\"", "\"");
                    $num_campos = count($nombres_campos);
                    
                    // Lee los registros
                    while (($datos = fgetcsv($fichero, 0, "|", "\"", "\"")) !== false) {
                        // Crea un array asociativo con los nombres y valores de los campos
                        for ($icampo = 0; $icampo < $num_campos; $icampo++) {
                            if (isset($datos[$icampo])) {
                                $registro[$nombres_campos[$icampo]] = $datos[$icampo];
                            }
                        }
                        
                        // Añade el registro leido al array de registros
                        $registros[] = $registro;
                    }
                    fclose($fichero);
                    
                    // obtenemos ultimo key del array y lo eliminamos por tener datos repetido
                    // $ultimoKey = count( $registros )-1;
                    // unset($registros[$ultimoKey]);

                    $db = Db::getInstance(); // instanciamos la db
                    // actualizando la db
                    foreach ($registros as $items) {

                        $cantidades = $items["Cantidad"];
                        $cantidades2 = explode(',', $cantidades);
                        $cantidad = (int) $cantidades2[0];
                        
                        $sql = "UPDATE "._DB_PREFIX_."stock_available A
                        INNER JOIN "._DB_PREFIX_."product B
                        ON  A.id_product = B.id_product
                        SET A.quantity=".$cantidad.", A.physical_quantity=".$cantidad." 
                        WHERE B.reference = '".$items["﻿Producto"]."';";
                        $db->execute($sql);
                        if (!Db::getInstance()->execute($sql)){
                            self::logtxt("Error al actualizar el inventario!");
                            // var_dump("Error al actualizar el inventario!");
                        }else {
                            self::logtxt("Inventario actualizado!");
                            // var_dump("Inventario actualizado!");
                        }
                    }

                    // echo "<hr><pre>";
                    // var_dump($registros);
                    // echo "</pre>";

                }
            }else {
                self::logtxt("El archivo: ".$nombre_fichero." no existe!");
                var_dump("El archivo: ".$nombre_fichero." no existe!");
            }
            
        }else {
            // var_dump('no funcional!');
        }
        }
        
    }
    
}
