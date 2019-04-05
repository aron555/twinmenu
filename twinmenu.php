<?php
/**
 * @package	HikaShop for Joomla!
 * @version	2.3.0
 * @author	progreccor
 * @copyright	(C) 2010-2017 PROGRECCOR SOFTWARE. All rights reserved.
 * @license	GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
defined('_JEXEC') or die('Restricted access');

/**
 * Plugin to for Hikashop
 * For more informations, see :
 * http://www.hikashop.com/support/documentation/62-hikashop-developer-documentation.html
 */


class plgHikashopTwinmenu extends JPlugin {

    protected $autoloadLanguage = true;
    protected $menu;
    protected $db;
    protected $app;
    protected $component_id;
    protected $menuType;
    protected $update;
    protected $update_product;
    protected $need_parent_alias;
    protected $hika_type_menu;
    protected $cols_pr;
    protected $rows_pr;
    protected $show_menu_not_main_category;
    protected $cols_cat;
    protected $rows_cat;
    protected $show_description;
    protected $div_item_layout_type;
    protected $id_main_category;
    protected $create_canonical_product;


    public function plgHikashopTwinmenu(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $this->menu = $this->params->get('menu', null);
        $this->component_id = JComponentHelper::getComponent('com_hikashop')->id;
        $this->update = $this->params->get('update', null);
        $this->update_product = $this->params->get('update_product', null);
        $this->need_parent_alias = $this->params->get('need_parent_alias', null);
        $this->hika_type_menu = $this->params->get('hika_type_menu', null);
        $this->cols_pr = $this->params->get('cols_pr', null);
        $this->rows_pr = $this->params->get('rows_pr', null);
        $this->show_menu_not_main_category = $this->params->get('show_menu_not_main_category', null);
        $this->cols_cat = $this->params->get('cols_cat', null);
        $this->rows_cat = $this->params->get('rows_cat', null);
        $this->show_description = $this->params->get('show_description', null);
        $this->div_item_layout_type = $this->params->get('div_item_layout_type', null); // вывод категории, title -  обращение, img_title - изображение и название
        $this->id_main_category = $this->params->get('id_main_category', null);//категория типа каталог
        $this->create_canonical_product = $this->params->get('create_canonical_product', null);
    }

    public function onAfterCategoryUpdate(&$element) {
        if (!empty($element->category_id)) {

            if ($this->update == "1") {

                mb_internal_encoding("UTF-8");
                $category_path = mb_substr($element->category_canonical, '1');//$category_path это каноникал категории без начального /

                $db = JFactory::getDBO();
                $query = $db->getQuery(true);

                $query
                    ->select($db->quoteName("id"))
                    ->from($db->quoteName("#__menu"))
                    ->where($db->quoteName('params') . ' LIKE ' . $db->quote('%\"category\":\"' . $element->category_id . '\"%'))
                    //->where($db->quoteName('path') . ' = ' . $db->quote($category_path))
                    ->where($db->quoteName('menutype') . ' = ' . $db->quote($this->menu))
                    ->where($db->quoteName('published') . ' >=  ' . $db->quote('0'));

                $db->setQuery($query);
                $data = $db->loadAssoc();/*Ищем пункт меню с этой категорией */
                $query->clear();
                if (count($data) > 0) {
                    //получаем alias родительской категории из имени
                    $query
                        ->select('category_name')
                        ->from($db->quoteName('#__hikashop_category'))
                        ->where($db->quoteName('category_parent_id') . ' > ' . $db->quote('1'))
                        ->where($db->quoteName('category_id') . ' = ' . $db->quote($element->category_parent_id));

                    $db->setQuery($query);
                    $parent_name = $db->loadResult();
                    $parent_alias = JApplicationHelper::stringURLSafe($parent_name, 'ru-RU');
                    $query->clear();

                    //получаем canonical родительской категории
                    $query
                        ->select('category_canonical')
                        ->from($db->quoteName('#__hikashop_category'))
                        ->where($db->quoteName('category_id') . ' = ' . $db->quote($element->category_parent_id));

                    $db->setQuery($query);
                    $parent_canonical = $db->loadResult();
                    $query->clear();

                    // Set alias

                    $category_alias = JApplicationHelper::stringURLSafe($element->category_name, 'ru-RU');

                    if ($parent_alias != "") {//если у родительской категории есть алиас
                        if ($this->need_parent_alias == "need_parent_alias") {//и включена опция С родительским алиасом
                            $full_alias = $full_category_alias = $parent_alias . '-' . $category_alias;//Алиас = "Алиас_родителя-алиас"
                        } else {
                            $full_category_alias = $parent_alias . '-' . $category_alias;//алиас для категории(должен быть уникальным)
                            $full_alias = $category_alias;//алиас для построения канонической ссылки категории
                        }
                    } else {
                        $full_alias = $full_category_alias = $category_alias;//Алиас, если алиас родителя пустой
                    }

                    $category_canonical = $parent_canonical . '/' . $full_alias;//canonical категории = "canonical родительской категории/алиас категории"


                    $fields = array(
                        $db->quoteName('category_alias') . ' = ' . $db->quote($full_category_alias),
                        $db->quoteName('category_canonical') . ' = ' . $db->quote($category_canonical),
                        //$db->quoteName('category_keywords') . ' = ' . $db->quote($parent_alias.', '.$category_alias.', '.$category->category_name),
                        //$db->quoteName('category_meta_description') . ' = ' . $db->quote($parent_alias.' '.$category_alias.' '.$category->category_name)
                    );

                    $query
                        ->update('#__hikashop_category')
                        ->set($fields)
                        ->where($db->quoteName('category_id') . ' = ' . $db->quote($element->category_id));
                    $db->setQuery($query);
                    $db->execute();
                    $query->clear();

                    /*теперь на основе каноничкого адреса категории создадим пункт меню с этой категорией*/

                    if ($this->show_menu_not_main_category == "0") {
                        $query
                            ->select($db->quoteName('category_canonical'))
                            ->from($db->quoteName('#__hikashop_category'))
                            ->where($db->quoteName('category_id') . ' = ' . $db->quote($this->id_main_category));
                        $db->setQuery($query);
                        $main_category_canonical = $db->loadResult();//каноническая ссылка категории типа каталог
                        $query->clear();

                        $query
                            ->select($db->quoteName("path"))
                            ->from($db->quoteName("#__menu"))
                            //->where($db->quoteName('path') . ' LIKE ' . $db->quote('%' . $element->category_canonical . '%'))
                            ->where($db->quoteName('params') . ' LIKE ' . $db->quote('%\"category\":\"' . $element->category_parent_id . '\"%'))
                            ->where($db->quoteName('menutype') . ' = ' . $db->quote($this->menu))
                            ->where($db->quoteName('published') . ' >=  ' . $db->quote('0'));
                        $db->setQuery($query);
                        $menu_path = $db->loadResult();//каноническая ссылка категории типа каталог
                        $query->clear();

                        mb_internal_encoding("UTF-8");
                        $main_category_path = mb_substr($main_category_canonical, '1');//$category_path это каноникал категории без начального /

                        if (strpos($menu_path, $main_category_path) !== false) // именно через жесткое сравнение
                        {
                            $menu_show = "1";
                        } else {
                            $menu_show = "0";
                        }
                    } else {
                        $menu_show = "1";
                    }


                    if ($this->hika_type_menu == "category") { //если выбрана категория, то добавляем для нее параметры
                        $hika_type = '{"hk_category":{"layout_type":"div","columns":"' . $this->cols_cat . '","rows":"' . $this->rows_cat . '","limit":"' . $this->cols_cat * $this->rows_cat . '","div_item_layout_type":"' . $this->div_item_layout_type . '","image_width":"","image_height":"","product_transition_effect":"linear","product_effect_duration":"","pane_height":"","text_center":"-1","show_description_listing":"0","consistencyheight":"1","background_color":"","margin":"","border_visible":"-1","rounded_corners":"-1","ul_class_name":"","ul_display_simplelist":"0","show_image":"0","show_description":"' . $this->show_description . '","category":"' . $element->category_id . '","category_order":"inherit","order_dir":"inherit","random":"-1","filter_type":"0","use_module_name":"0","child_display_type":"inherit","child_limit":"","number_of_products":"-1","only_if_products":"-1"},"hk_product":{"layout_type":"inherit","columns":"' . $this->cols_pr . '","rows":"' . $this->rows_pr . '","limit":"' . $this->cols_pr * $this->rows_pr . '","div_item_layout_type":"inherit","image_width":"","image_height":"","product_transition_effect":"linear","product_effect_duration":"","pane_height":"","text_center":"-1","show_description_listing":"0","consistencyheight":"1","infinite_scroll":"0","background_color":"","margin":"","border_visible":"-1","rounded_corners":"-1","ul_class_name":"","product_order":"inherit","order_dir":"inherit","random":"-1","filter_type":"2","use_module_name":"0","discounted_only":"0","related_products_from_cart":"0","show_out_of_stock":"-1","link_to_product_page":"-1","show_price":"-1","price_display_type":"inherit","price_with_tax":"3","show_original_price":"-1","show_discount":"3","add_to_cart":"-1","add_to_wishlist":"-1","show_quantity_field":"-1","product_waitlist":"0","show_vote":"-1","display_custom_item_fields":"-1","display_filters":"-1","display_badges":"-1"},"menu-anchor_title":"","menu-anchor_css":"","menu_image":"","menu_image_css":"","menu_text":1,"menu_show":' . $menu_show . ',"page_title":"","show_page_heading":"","page_heading":"","pageclass_sfx":"","menu-meta_description":"","menu-meta_keywords":"","robots":"","secure":0}';
                        $link = 'index.php?option=com_hikashop&view=category&layout=listing';
                    }
                    if ($this->hika_type_menu == "product") {
                        $hika_type = '{"hk_product":{"layout_type":"inherit","columns":"' . $this->cols_pr . '","rows":"' . $this->rows_pr . '","limit":"' . $this->cols_pr * $this->rows_pr . ',"div_item_layout_type":"inherit","image_width":"","image_height":"","product_transition_effect":"linear","product_effect_duration":"","pane_height":"","text_center":"-1","show_description_listing":"0","consistencyheight":"1","infinite_scroll":"0","background_color":"","margin":"","border_visible":"-1","rounded_corners":"-1","ul_class_name":"","show_image":"0","show_description":"0","category":"' . $element->category_id . '","product_order":"inherit","order_dir":"inherit","random":"-1","filter_type":"0","use_module_name":"0","discounted_only":"0","related_products_from_cart":"0","show_out_of_stock":"-1","recently_viewed":"-1","link_to_product_page":"-1","show_price":"-1","price_display_type":"inherit","price_with_tax":"3","show_original_price":"-1","show_discount":"3","add_to_cart":"-1","add_to_wishlist":"-1","show_quantity_field":"-1","show_vote":"-1","display_custom_item_fields":"-1","display_filters":"-1","display_badges":"-1"},"menu-anchor_title":"","menu-anchor_css":"","menu_image":"","menu_image_css":"","menu_text":1,"menu_show":' . $menu_show . ',"page_title":"","show_page_heading":"","page_heading":"","pageclass_sfx":"","menu-meta_description":"","menu-meta_keywords":"","robots":"","secure":0}';
                        $link = 'index.php?option=com_hikashop&view=product&layout=listing';
                    }

                    $menu_id = $data["id"];

                    $menuTable = JTableNested::getInstance('Menu');
                    $menuTable->load($menu_id);

                    $menuData = array(
                        'title' => $element->category_name,
                        'alias' => $full_alias,
                        'link' => $link, /* URL  меню   например  :index.php?com_yourcomponent&......     */
                        'published' => $element->category_published,
                        'params' => $hika_type
                    );


                    if (!$menuTable->save($menuData)) {
                        $this->app->enqueueMessage("Ошибка!!", "error");
                        return false;
                    } else {
                        $this->app->enqueueMessage("Пункт меню <strong>" . $element->category_name . "</strong> в меню <strong>" . $this->menu . "</strong> обновлён.");
                        return true;
                    }
                } else {
                    return $this->onAfterCategoryCreate($element);
                    //$this->app->enqueueMessage("Соответствующий пункт меню не найден.", "warning");

                }

            }
        }
    }

    public function onAfterCategoryDelete(&$ids) {

        $menuTable = JTableNested::getInstance('Menu');

        $db = JFactory::getDBO();
        $query = $db->getQuery(true);

        foreach ($ids as $id) {

            mb_internal_encoding("UTF-8");
            $category_path = mb_substr($id->category_canonical, '1');//$category_path это каноникал категории без начального /

            $query
                ->select($db->quoteName(array("id, title")))
                ->from($db->quoteName("#__menu"))
                ->where($db->quoteName('path') . ' = ' . $db->quote($category_path))
                ->where($db->quoteName('params')  . ' LIKE '.  $db->quote('%\"category\":\"' . $id . '\"%'))
                ->where($db->quoteName('menutype') . ' = ' . $db->quote($this->menu))
                ->where($db->quoteName('published')  . ' >=  '. $db->quote('0'));

            try
            {
                $db->setQuery($query);
                $data = $db->loadAssoc();
                $query->clear();
                if(count($data)>0) {
                    $menu_id=$data["id"];
                    if($menuTable->delete($menu_id,false)) {
                        $this->app->enqueueMessage("Пункт меню <strong>".$data["title"]."</strong> удален.", "message");
                    } else {
                        $this->app->enqueueMessage("Соответствующий пункт меню не найден.", "warning");
                    }
                } else {
                    $this->app->enqueueMessage("Соответствующий пункт меню не найден.", "warning");
                }

            }
            catch (RuntimeException $e)
            {
                $this->app->enqueueMessage("Ошибка при удалении.","error");
            }

        }

        $menuTable->rebuild();

    }

    public function onAfterCategoryCreate(&$element)
    {
        if (!empty($element->category_id))
        {
            $db = JFactory::getDBO();
            $query = $db->getQuery(true);

            //получаем alias родительской категории из имени
            $query
                ->select('category_name')
                ->from($db->quoteName('#__hikashop_category'))
                ->where($db->quoteName('category_parent_id') . ' > ' . $db->quote('1'))
                ->where($db->quoteName('category_id') . ' = ' . $db->quote($element->category_parent_id));

            $db->setQuery($query);
            $parent_alias = JApplicationHelper::stringURLSafe($db->loadResult(), 'ru-RU');
            $query->clear();

            //получаем canonical родительской категории
            $query
                ->select('category_canonical')
                ->from($db->quoteName('#__hikashop_category'))
                ->where($db->quoteName('category_id') . ' = ' . $db->quote($element->category_parent_id));

            $db->setQuery($query);
            $parent_canonical = $db->loadResult();
            $query->clear();

            // Set alias

            $category_alias = JApplicationHelper::stringURLSafe($element->category_name, 'ru-RU');

            if ($parent_alias != "") {//если у родительской категории есть алиас
                if ($this->need_parent_alias == "need_parent_alias") {//и включена опция С родительским алиасом
                    $full_alias = $full_category_alias = $parent_alias . '-' . $category_alias;//Алиас = "Алиас_родителя-алиас"
                } else {
                    $full_category_alias = $parent_alias . '-' . $category_alias;//алиас для категории(должен быть уникальным)
                    $full_alias = $category_alias;//алиас для построения канонической ссылки категории
                }
            } else {
                $full_alias = $full_category_alias = $category_alias;//Алиас, если алиас родителя пустой
            }

            $category_canonical =  $parent_canonical . '/' . $full_alias;//canonical категории = "canonical родительской категории/алиас категории"


            $fields = array(
                $db->quoteName('category_alias') . ' = ' . $db->quote($full_category_alias),
                $db->quoteName('category_canonical') . ' = ' . $db->quote($category_canonical),
                //$db->quoteName('category_keywords') . ' = ' . $db->quote($parent_alias.', '.$category_alias.', '.$category->category_name),
                //$db->quoteName('category_meta_description') . ' = ' . $db->quote($parent_alias.' '.$category_alias.' '.$category->category_name)
            );

            $query
                ->update('#__hikashop_category')
                ->set($fields)
                ->where($db->quoteName('category_id') . ' = ' . $db->quote($element->category_id));
            $db->setQuery($query);
            $db->execute();
            $query->clear();




            /*теперь на основе каноничкого адреса категории создадим пункт меню с этой категорией*/

            if ($this->show_menu_not_main_category == "0") {
                $query
                    ->select($db->quoteName('category_canonical'))
                    ->from($db->quoteName('#__hikashop_category'))
                    ->where($db->quoteName('category_id') . ' = ' . $db->quote($this->id_main_category))
                ;
                $db->setQuery($query);
                $main_category_canonical  = $db->loadResult();//каноническая ссылка категории типа каталог
                $query->clear();

                $query
                    ->select($db->quoteName("path"))
                    ->from($db->quoteName("#__menu"))
                    //->where($db->quoteName('path') . ' LIKE ' . $db->quote('%' . $element->category_canonical . '%'))
                    ->where($db->quoteName('params')  . ' LIKE '.  $db->quote('%\"category\":\"' . $element->category_id . '\"%'))
                    ->where($db->quoteName('menutype') . ' = ' . $db->quote($this->menu))
                    //->where($db->quoteName('published')  . ' >=  '. $db->quote('0'))
                ;
                $db->setQuery($query);
                $menu_path  = $db->loadResult();//path родительского пункта меню
                $query->clear();

                mb_internal_encoding("UTF-8");
                $main_category_path = mb_substr($main_category_canonical, '1');//$category_path это каноникал категории без начального /

                if (strpos($menu_path, $main_category_path) !== false) //если каноническая ссылка типа каталог является частью path родительского пункта меню
                {
                    $menu_show = "1";
                } else {
                    $menu_show = "0";
                }
            } else {
                $menu_show = "1";
            }

            if ($this->hika_type_menu == "category") { //если выбрана категория, то добавляем для нее параметры
                $hika_type = '{"hk_category":{"layout_type":"div","columns":"'.$this->cols_cat.'","rows":"'.$this->rows_cat.'","limit":"'.$this->cols_cat*$this->rows_cat.'","div_item_layout_type":"'.$this->div_item_layout_type.'","image_width":"","image_height":"","product_transition_effect":"linear","product_effect_duration":"","pane_height":"","text_center":"-1","show_description_listing":"0","consistencyheight":"1","background_color":"","margin":"","border_visible":"-1","rounded_corners":"-1","ul_class_name":"","ul_display_simplelist":"0","show_image":"0","show_description":"'.$this->show_description.'","category":"'.$element->category_id.'","category_order":"inherit","order_dir":"inherit","random":"-1","filter_type":"0","use_module_name":"0","child_display_type":"inherit","child_limit":"","number_of_products":"-1","only_if_products":"-1"},"hk_product":{"layout_type":"inherit","columns":"'.$this->cols_pr.'","rows":"'.$this->rows_pr.'","limit":"'.$this->cols_pr*$this->rows_pr.'","div_item_layout_type":"inherit","image_width":"","image_height":"","product_transition_effect":"linear","product_effect_duration":"","pane_height":"","text_center":"-1","show_description_listing":"0","consistencyheight":"1","infinite_scroll":"0","background_color":"","margin":"","border_visible":"-1","rounded_corners":"-1","ul_class_name":"","product_order":"inherit","order_dir":"inherit","random":"-1","filter_type":"2","use_module_name":"0","discounted_only":"0","related_products_from_cart":"0","show_out_of_stock":"-1","link_to_product_page":"-1","show_price":"-1","price_display_type":"inherit","price_with_tax":"3","show_original_price":"-1","show_discount":"3","add_to_cart":"-1","add_to_wishlist":"-1","show_quantity_field":"-1","product_waitlist":"0","show_vote":"-1","display_custom_item_fields":"-1","display_filters":"-1","display_badges":"-1"},"menu-anchor_title":"","menu-anchor_css":"","menu_image":"","menu_image_css":"","menu_text":1,"menu_show":'.$menu_show.',"page_title":"","show_page_heading":"","page_heading":"","pageclass_sfx":"","menu-meta_description":"","menu-meta_keywords":"","robots":"","secure":0}';
                $link = 'index.php?option=com_hikashop&view=category&layout=listing';
            }
            if ($this->hika_type_menu == "product") {
                $hika_type = '{"hk_product":{"layout_type":"inherit","columns":"'.$this->cols_pr.'","rows":"'.$this->rows_pr.'","limit":"'.$this->cols_pr*$this->rows_pr.',"div_item_layout_type":"inherit","image_width":"","image_height":"","product_transition_effect":"linear","product_effect_duration":"","pane_height":"","text_center":"-1","show_description_listing":"0","consistencyheight":"1","infinite_scroll":"0","background_color":"","margin":"","border_visible":"-1","rounded_corners":"-1","ul_class_name":"","show_image":"0","show_description":"0","category":"'.$element->category_id.'","product_order":"inherit","order_dir":"inherit","random":"-1","filter_type":"0","use_module_name":"0","discounted_only":"0","related_products_from_cart":"0","show_out_of_stock":"-1","recently_viewed":"-1","link_to_product_page":"-1","show_price":"-1","price_display_type":"inherit","price_with_tax":"3","show_original_price":"-1","show_discount":"3","add_to_cart":"-1","add_to_wishlist":"-1","show_quantity_field":"-1","show_vote":"-1","display_custom_item_fields":"-1","display_filters":"-1","display_badges":"-1"},"menu-anchor_title":"","menu-anchor_css":"","menu_image":"","menu_image_css":"","menu_text":1,"menu_show":'.$menu_show.',"page_title":"","show_page_heading":"","page_heading":"","pageclass_sfx":"","menu-meta_description":"","menu-meta_keywords":"","robots":"","secure":0}';
                $link = 'index.php?option=com_hikashop&view=product&layout=listing';
            }

            $menuTable = JTableNested::getInstance('Menu');
            $menuData  = array(
                'menutype'     => $this->menu, /* Тип меню должен быть латиницей и быть уникальным*/
                'title'        => $element->category_name,
                'alias'        => $full_alias,
                'link'         => $link, /* URL  меню   например  :index.php?com_yourcomponent&......     */
                'type'         => 'component',  /* внутренний тип меню*/
                'component_id' => $this->component_id,     /* ID компонента в #__extensions  */
                'language'     => '*',
                'published'    => $element->category_published,
                'params'       => $hika_type
            );

            if ($element->category_parent_id == 1)/* если это лежит  в главной категории (каталог) */
            {
                $parent_id = 1;// то родительский элемент пункта меню - корневой
            } else
            {
                $query
                    ->select($db->quoteName("id"))
                    ->from($db->quoteName("#__menu"))
                    //->where($db->quoteName('path') . ' LIKE ' . $db->quote('%' . $element->category_canonical . '%'))
                    ->where($db->quoteName('params')  . ' LIKE '.  $db->quote('%\"category\":\"' . $element->category_parent_id . '\"%'))
                    ->where($db->quoteName('menutype') . ' = ' . $db->quote($this->menu))
                    ->where($db->quoteName('published')  . ' >=  '. $db->quote('0'));

                try
                {
                    $db->setQuery($query);
                    $data = $db->loadAssoc();
                    $query->clear();
                    if (count($data) > 0)
                    {
                        $parent_id = $data["id"];
                    }
                    else
                    {
                        $this->app->enqueueMessage("Родительский пункт меню для <strong>" . $element->category_name . "</strong> не найден. Объект помещен в корень меню.", "warning");
                        $parent_id = 1;
                    }

                }
                catch (RuntimeException $e)
                {
                    $this->app->enqueueMessage("Ошибка.", "error");
                }
            }

            $menuTable->setLocation($parent_id, 'last-child');
            if (!$menuTable->save($menuData))
            {
                $this->app->enqueueMessage("Ошибка.", "error");

                return false;
            }
            $this->app->enqueueMessage("Пункт меню <strong>" . $element->category_name . "</strong> в меню <strong>" . $this->menu . "</strong> создан.");

            return true;

        } else {
            $this->app->enqueueMessage("Это старая версия hikashop. Обновите. ", "warning");
        }
    }


    public function onAfterProductCreate(&$element)
    {

        if (!empty($element->product_id) && $this->create_canonical_product == "1") {
            $db = JFactory::getDBO();
            $query = $db->getQuery(true);

            //Если у продукта всего одна категория, то все просто, берем ее
            if (count($element->categories) == 1) {

                $query
                    ->select($db->quoteName('#__hikashop_category.category_canonical'))
                    ->from($db->quoteName('#__hikashop_category'))
                    ->where($db->quoteName('category_id') . ' = ' . $db->quote($element->categories[0]));
                $db->setQuery($query);
                $category_canonical = $db->loadResult();
                $query->clear();

            } else {
                /*иначе сложнее. будем искать максимально глубоко вложенную категорию только внутри категории типа каталог */

                $query
                    ->select($db->quoteName('#__hikashop_category.category_canonical'))
                    ->from($db->quoteName('#__hikashop_category'))
                    ->where($db->quoteName('category_id') . ' = ' . $db->quote($this->id_main_category));
                $db->setQuery($query);
                $main_category_canonical = $db->loadResult();//каноническая ссылка категории типа каталог
                $query->clear();

                $query
                    ->select($db->quoteName('#__hikashop_category.category_depth'))
                    ->from($db->quoteName('#__hikashop_category'))
                    ->where($db->quoteName('category_id') . ' IN (' . implode(',', $element->categories) . ')')
                    ->where($db->quoteName('category_canonical') . ' LIKE ' . $db->quote('%' . $main_category_canonical . '%'));
                $db->setQuery($query);
                $depths = $db->loadObjectList();
                $maxdepth = max($depths)->category_depth;//наибольшая вложенность среди всех категорий продукта, но в категории типа каталог
                $query->clear();

                $query
                    ->select($db->quoteName('#__hikashop_category.category_canonical'))
                    ->from($db->quoteName('#__hikashop_category'))
                    ->where($db->quoteName('category_depth') . ' = ' . $db->quote($maxdepth))
                    ->where($db->quoteName('category_id') . ' IN (' . implode(',', $element->categories) . ')')
                    ->where($db->quoteName('category_canonical') . ' LIKE ' . $db->quote('%' . $main_category_canonical . '%'));
                $db->setQuery($query);
                $category_canonical = $db->loadResult();//канонический категории (каталог) наибольшей вложенности
                $query->clear();
            }

            /*на основе канонической ссылки категории, получаем алиас товара*/
            $product_canonical = $category_canonical . '/' . JApplicationHelper::stringURLSafe($element->product_name, 'ru-RU'); //каноничский продукта

            $fields = array(
                $db->quoteName('product_canonical') . ' = ' . $db->quote($product_canonical)
            );

            $query
                ->update('#__hikashop_product')
                ->set($fields)
                ->where($db->quoteName('product_id') . ' = ' . $db->quote($element->product_id));
            $db->setQuery($query);
            $db->execute();
            $query->clear();
        }
    }


    public function onAfterProductUpdate(&$element)
    {
        if (!empty($element->product_id) && $this->update_product == "1") {

            $db = JFactory::getDBO();
            $query = $db->getQuery(true);

            //Если у продукта всего одна категория, то все просто, берем ее
            if (count($element->categories) == 1) {
                $query
                    ->select($db->quoteName('#__hikashop_category.category_canonical'))
                    ->from($db->quoteName('#__hikashop_category'))
                    ->where($db->quoteName('category_id') . ' = ' . $db->quote($element->categories[0]));
                $db->setQuery($query);
                $category_canonical = $db->loadResult();
                $query->clear();

            } else {
                /*иначе сложнее. будем искать максимально глубоко вложенную категорию только внутри категории типа каталог */

                $query
                    ->select($db->quoteName('#__hikashop_category.category_canonical'))
                    ->from($db->quoteName('#__hikashop_category'))
                    ->where($db->quoteName('category_id') . ' = ' . $db->quote($this->id_main_category));
                $db->setQuery($query);
                $main_category_canonical = $db->loadResult();//каноническая ссылка категории типа каталог
                $query->clear();

                $query
                    ->select($db->quoteName('#__hikashop_category.category_depth'))
                    ->from($db->quoteName('#__hikashop_category'))
                    ->where($db->quoteName('category_id') . ' IN (' . implode(',', $element->categories) . ')')
                    ->where($db->quoteName('category_canonical') . ' LIKE ' . $db->quote('%' . $main_category_canonical . '%'));
                $db->setQuery($query);
                $depths = $db->loadObjectList();
                $maxdepth = max($depths)->category_depth;//наибольшая вложенность среди всех категорий продукта, но в категории типа каталог
                $query->clear();

                $query
                    ->select($db->quoteName('#__hikashop_category.category_canonical'))
                    ->from($db->quoteName('#__hikashop_category'))
                    ->where($db->quoteName('category_depth') . ' = ' . $db->quote($maxdepth))
                    ->where($db->quoteName('category_id') . ' IN (' . implode(',', $element->categories) . ')')
                    ->where($db->quoteName('category_canonical') . ' LIKE ' . $db->quote('%' . $main_category_canonical . '%'));
                $db->setQuery($query);
                $category_canonical = $db->loadResult();//каноническая ссылка категории (каталог) наибольшей вложенности
                $query->clear();
            }

            /*на основе канонической ссылки категории, получаем алиас товара*/
            $product_canonical = $category_canonical . '/' . JApplicationHelper::stringURLSafe($element->product_name, 'ru-RU'); //каноничский продукта

            $fields = array(
                $db->quoteName('product_alias') . ' = ' . $db->quote(JApplicationHelper::stringURLSafe($element->product_name, 'ru-RU')),
                $db->quoteName('product_canonical') . ' = ' . $db->quote($product_canonical)
            );

            $query
                ->update('#__hikashop_product')
                ->set($fields)
                ->where($db->quoteName('product_id') . ' = ' . $db->quote($element->product_id));
            $db->setQuery($query);
            $db->execute();
            $query->clear();

        }

    }
}
