<?php
/**
 *
 *  @copyright 2008 - https://www.clicshopping.org
 *  @Brand : ClicShopping(Tm) at Inpi all right Reserved
 *  @Licence GPL 2 & MIT
 *  @licence MIT - Portion of osCommerce 2.4
 *  @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

  use ClicShopping\OM\HTML;
  use ClicShopping\OM\Registry;
  use ClicShopping\OM\HTTP;
  use ClicShopping\OM\CLICSHOPPING;

  class ht_google_analytics {
    public $code;
    public $group;
    public $title;
    public $description;
    public $sort_order;
    public $enabled = false;

    public function __construct() {
      $this->code = get_class($this);
      $this->group = basename(__DIR__);
      $this->title = CLICSHOPPING::getDef('module_header_tags_google_analytics_title');
      $this->description = CLICSHOPPING::getDef('module_header_tags_google_analytics_description');

      if ( defined('MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_STATUS') ) {
        $this->sort_order = MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_SORT_ORDER;
        $this->enabled = (MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_STATUS == 'True');
      }
    }

    public function execute() {

      $CLICSHOPPING_Customer = Registry::get('Customer');
      $CLICSHOPPING_Db = Registry::get('Db');
      $CLICSHOPPING_Template = Registry::get('Template');

      if (!is_null(MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_ID) ) {
        if (MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_JS_PLACEMENT != 'Header') {
          $this->group = 'footer_scripts';
        }

        $header = '<!-- Google Analytics Start -->' . "\n";
        $header .= '<script>';
        $header .= "  (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o), m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m) })(window,document,'script','https://www.google-analytics.com/analytics.js','ga'); ga('create', '" . HTML::output(MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_ID) . "', 'auto');";
        $header .= "  ga('send', 'pageview');";

        if ( (MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_EC_TRACKING == 'True') && isset($_GET['Checkout']) && isset($_GET['Success']) && $CLICSHOPPING_Customer->isLoggedOn() ) {

          $Qorder = $CLICSHOPPING_Db->prepare('select orders_id,
                                              billing_city,
                                              billing_state,
                                              billing_country
                                       from :table_orders
                                       where customers_id = :customers_id
                                       order by date_purchased desc limit 1
                                        ');

          $Qorder->bindInt(':customers_id',  (int)$CLICSHOPPING_Customer->getID() );
          $Qorder->execute();

          if ($Qorder->rowCount() == 1) {
            $order =  $Qorder->fetch();

            $totals = [];

            $QorderTotals = $CLICSHOPPING_Db->prepare('select value,
                                                       class
                                                from :table_orders_total
                                                where orders_id = :orders_id
                                        ');

            $QorderTotals->bindInt(':orders_id', (int)$order['orders_id'] );
            $QorderTotals->execute();


            while ($order_totals = $QorderTotals->fetch() ) {
              $totals[$order_totals['class']] = $order_totals['value'];
            }

            $header .= "  ga('require', 'ecommerce', 'ecommerce.js'); ";
            $header .= "  ga('ecommerce:addTransaction',{ ";
            $header .= "  'id': '" . (int)$order['orders_id'] . "', ";
            $header .= "  'affiliation': '" . STORE_NAME . "', ";
            $header .= "  'affiliation': '" . str_replace('http://', '', str_replace('www.', '', HTTP::typeUrlDomain())) . "', ";

            if (isset($totals['ot_total'])) {
              $total = isset($totals['ot_total']);
            } elseif (isset($totals['TO'])) {
              $total = isset($totals['TO']);
            }

            if (isset($totals['ot_shipping'])) {
              $shipping = isset($totals['ot_shipping']);
            } elseif (isset($totals['SH'])) {
              $shipping = isset($totals['SH']);
            }

            if (isset($totals['ot_tax'])) {
              $tax = isset($totals['ot_tax']);
            } elseif (isset($totals['TX'])) {
              $tax = isset($totals['TX']);
            }

            $header .= "  'revenue': '" . ($total ? $this->format_raw($total, DEFAULT_CURRENCY) : 0) . "', ";
            $header .= "  'shipping': '" . ($shipping ? $this->format_raw($shipping, DEFAULT_CURRENCY) : 0) . "', ";
            $header .= "  'tax': '" . ($tax ? $this->format_raw($tax, DEFAULT_CURRENCY) : 0) . "' ";
            $header .= "  });" . "\n";

            $QorderProducts = $CLICSHOPPING_Db->prepare('select op.products_id,
                                                                 pd.products_name,
                                                                 op.final_price,
                                                                 op.products_quantity
                                                          from :table_orders_products op,
                                                               :table_products_description pd,
                                                               :table_languages  l
                                                          where op.orders_id = :orders_id
                                                          and op.products_id = pd.products_id
                                                          and l.code = :code
                                                          and l.languages_id = pd.language_id
                                                       ');

            $QorderProducts->bindInt(':orders_id', (int)$order['orders_id'] );
            $QorderProducts->bindValue(':code',  DEFAULT_LANGUAGE );

            $QorderProducts->execute();

            while ($order_products = $QorderProducts->fetch() ) {

              $Qcategory = $CLICSHOPPING_Db->prepare('select cd.categories_name
                                                from categories_description cd,
                                                     products_to_categories p2c,
                                                     languages  l
                                                where p2c.products_id = :products_id
                                                and p2c.categories_id = cd.categories_id
                                                and l.code = :code
                                                and l.languages_id = cd.language_id limit 1
                                               ');

              $Qcategory->bindInt(':products_id', (int)$order_products['products_id'] );
              $Qcategory->bindValue(':code',  DEFAULT_LANGUAGE );

              $Qcategory->execute();

              $category = $Qcategory->fetch();

              $header .= "  ga('ecommerce:addItem', { ";
              $header .= "  'id': '" . (int)$order['orders_id'] . "', ";
              $header .= "  'sku': '" . (int)$order_products['products_id'] . "', ";
              $header .= "  'name': '" . HTML::output($order_products['products_name']) . "', ";
              $header .= "  'category': '" . HTML::output($category['categories_name']) . "', ";
              $header .= "  'price': '" . $this->format_raw($order_products['final_price']) . "', ";
              $header .= "  'quantity': '" . (int)$order_products['products_quantity'] . "' ";
              $header .= "  });";
            }

            $header .= "  ga('ecommerce:send');" . "\n";
          }
        }

        $header .= '</script>' . "\n";
        $header .= '<!-- End Google Analytics  -->' . "\n";

        $CLICSHOPPING_Template->addBlock($header, $this->group);
      }
    }

    public function format_raw($number, $currency_code = '', $currency_value = '') {

      $CLICSHOPPING_Currencies = Registry::get('Currencies');

      if (empty($currency_code) || !$CLICSHOPPING_Currencies->is_set($currency_code)) {
        $currency_code = $_SESSION['currency'];
      }

      if (empty($currency_value) || !is_numeric($currency_value)) {
        $currency_value = $CLICSHOPPING_Currencies->currencies[$currency_code]['value'];
      }

      return number_format(round($number * $currency_value, $CLICSHOPPING_Currencies->currencies[$currency_code]['decimal_places']), $CLICSHOPPING_Currencies->currencies[$currency_code]['decimal_places'], '.', '');
    }

    public function isEnabled() {
      return $this->enabled;
    }

    public function check() {
      return defined('MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_STATUS');
    }

    public function install() {

      $CLICSHOPPING_Db = Registry::get('Db');
      $CLICSHOPPING_Language = Registry::get('Language');

      if ($CLICSHOPPING_Language->getId() =='1') {

        $CLICSHOPPING_Db->save('configuration', [
            'configuration_title' => 'Souhaitez-vous inclure la gestion des statistiques par Google',
            'configuration_key' => 'MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_STATUS',
            'configuration_value' => 'True',
            'configuration_description' => 'Souhaitez-vous inclure la gestion des statistiques avec Google Analytics ?',
            'configuration_group_id' => '6',
            'sort_order' => '1',
            'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
            'date_added' => 'now()'
          ]
        );

        $CLICSHOPPING_Db->save('configuration', [
            'configuration_title' => 'Souhaitez-vous insérer l\'UA de Google Analytics (Gestion des statistiques) ?',
            'configuration_key' => 'MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_ID',
            'configuration_value' => '',
            'configuration_description' => 'Veuillez insérer l\'UA (UA-XXXXXX-X) que google analytics vous a fourni. Vous trouverez cet élément lors de la génération du code dans le panel de google analytics',
            'configuration_group_id' => '6',
            'sort_order' => '2',
            'date_added' => 'now()'
          ]
        );

        $CLICSHOPPING_Db->save('configuration', [
            'configuration_title' => 'Souhaitez-vous activer le  E-Commerce Tracking',
            'configuration_key' => 'MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_EC_TRACKING',
            'configuration_value' => 'True',
            'configuration_description' => 'Do you want to enable e-commerce tracking? (E-Commerce tracking doit etre aussi validé dans votre gestion de profile de Google Analytics)',
            'configuration_group_id' => '6',
            'sort_order' => '0',
            'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
            'date_added' => 'now()'
          ]
        );

        $CLICSHOPPING_Db->save('configuration', [
            'configuration_title' => 'Emplacement du Javascript',
            'configuration_key' => 'MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_EC_TRACKING',
            'configuration_value' => 'Header',
            'configuration_description' => 'Ou souhaitez-vous placer le javavascript : en entete (header) ou en pied de page (footer)?',
            'configuration_group_id' => '6',
            'sort_order' => '0',
            'set_function' => 'clic_cfg_set_boolean_value(array(\'Header\', \'Footer\'))',
            'date_added' => 'now()'
          ]
        );

        $CLICSHOPPING_Db->save('configuration', [
            'configuration_title' => 'Ordre de tri d\'affichage',
            'configuration_key' => 'MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_SORT_ORDER',
            'configuration_value' => '90',
            'configuration_description' => 'Ordre de tri pour l\'affichage (Le plus petit nombre est montré en premier)',
            'configuration_group_id' => '6',
            'sort_order' => '70',
            'set_function' => '',
            'date_added' => 'now()'
          ]
        );

      } else {

       $CLICSHOPPING_Db->save('configuration', [
          'configuration_title' => 'Enable Google Analytics Module',
          'configuration_key' => 'MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_STATUS',
          'configuration_value' => 'True',
          'configuration_description' => 'Do you want to add Google Analytics to your shop?',
          'configuration_group_id' => '6',
          'sort_order' => '1',
          'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
          'date_added' => 'now()'
        ]
      );

      $CLICSHOPPING_Db->save('configuration', [
          'configuration_title' => 'Google Analytics ID',
          'configuration_key' => 'MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_ID',
          'configuration_value' => '',
          'configuration_description' => 'The Google Analytics profile ID to track.',
          'configuration_group_id' => '6',
          'sort_order' => '0',
          'date_added' => 'now()'
        ]
      );

      $CLICSHOPPING_Db->save('configuration', [
          'configuration_title' => 'E-Commerce Tracking',
          'configuration_key' => 'MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_EC_TRACKING',
          'configuration_value' => 'True',
          'configuration_description' => 'Do you want to enable e-commerce tracking? (E-Commerce tracking must also be enabled in your Google Analytics profile settings)',
          'configuration_group_id' => '6',
          'sort_order' => '0',
          'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
          'date_added' => 'now()'
        ]
      );

      $CLICSHOPPING_Db->save('configuration', [
          'configuration_title' => 'Javascript Placement',
          'configuration_key' => 'MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_JS_PLACEMENT',
          'configuration_value' => 'Header',
          'configuration_description' => 'Should the Google Analytics javascript be loaded in the header or footer?',
          'configuration_group_id' => '6',
          'sort_order' => '0',
          'set_function' => 'clic_cfg_set_boolean_value(array(\'Header\', \'Footer\'))',
          'date_added' => 'now()'
        ]
      );

        $CLICSHOPPING_Db->save('configuration', [
          'configuration_title' => 'Sort Order',
          'configuration_key' => 'MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_SORT_ORDER',
          'configuration_value' => '90',
          'configuration_description' => 'Sort order of display. Lowest is displayed first.',
          'configuration_group_id' => '6',
          'sort_order' => '70',
          'date_added' => 'now()'
        ]
      );

    }

      return $CLICSHOPPING_Db->save('configuration', ['configuration_value' => '1'],
                                               ['configuration_key' => 'WEBSITE_MODULE_INSTALLED']
                            );
    }

    public function remove() {
      return Registry::get('Db')->exec('delete from :table_configuration where configuration_key in ("' . implode('", "', $this->keys()) . '")');
    }

    public function keys() {
      return array('MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_STATUS',
                   'MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_ID',
                   'MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_EC_TRACKING',
                   'MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_JS_PLACEMENT',
                   'MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_SORT_ORDER'
                  );
    }
  }
