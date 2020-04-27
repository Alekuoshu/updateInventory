<?php
/**
* 2007-2019 PrestaShop
*
*  @author    Farmalisto <alejandro.villegas@farmalisto.com.co>
*  @copyright 2007-2019 Farmalisto
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

$sql = array();

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}
