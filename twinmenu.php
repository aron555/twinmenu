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
    protected $update_canonical_product_when_change_category;
    protected $update_category;
    protected $update_menu;
    protected $conditions_menu;
    protected $create_category;
    protected $mass_update;
    protected $hika_config;
    protected $jconfig;
    protected $siteName;
    protected $siteDesc;
    protected $MetaKeys;
    protected $categoryMetaKeywords;
    protected $product_meta_description_and_keywords;
    protected $exclude;
    protected $filter_type;
    protected $category_page_title_before_words;
    protected $category_page_title_after_words;
    protected $category_page_meta_description_before_words;
    protected $category_page_meta_description_after_words;
    protected $category_description_heading;


    public function plgHikashopTwinmenu(&$subject, $config)
    {
        parent::__construct($subject, $config);
        mb_internal_encoding("UTF-8");
        $this->menu = $this->params->get('menu', null);
        $this->component_id = JComponentHelper::getComponent('com_hikashop')->id;
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
        $this->create_canonical_product = $this->params->get('create_canonical_product', null);
        $this->update_canonical_product_when_change_category = $this->params->get('update_canonical_product_when_change_category', null);
        $this->update_category = $this->params->get('update_category', null);
        $this->update_menu = $this->params->get('update_menu', null);
        $this->conditions_menu = $this->params->get('conditions_menu', null);
        $this->create_category = $this->params->get('create_category', null);
        $this->mass_update = $this->params->get('mass_update', null);

        //$this->id_main_category = $this->params->get('id_main_category', null);//категория типа каталог
        $this->hika_config = hikashop_config();
        $default_params = $this->hika_config->get('default_params', '');//
        $this->id_main_category = $default_params['selectparentlisting'];//Главная категория

        $this->filter_type = $default_params['filter_type'];//Отображать подкатегории

        $this->category_description_heading = $this->params->get('category_description_heading', null);

        $this->jconfig = JFactory::getConfig();
        $this->siteName = htmlspecialchars($this->jconfig->get('sitename'));
        $this->siteDesc = htmlspecialchars($this->jconfig->get('MetaDesc'));
        $this->MetaKeys = htmlspecialchars($this->jconfig->get('MetaKeys'));

        $this->categoryMetaKeywords = $this->params->get('categoryMetaKeywords', null);
        $this->product_meta_description_and_keywords = $this->params->get('product_meta_description_and_keywords', null);

        $pretexts = array("в", "без", "до", "из", "к", "на", "по", "о", "от", "перед", "при", "через", "с", "у", "и", "нет", "за", "над", "для", "об", "под", "про", "-");

        function add_spaces(&$value)
        {
            $value = " ".$value." ";
        }

        array_walk($pretexts, 'add_spaces');
        $this->exclude = $pretexts;

        /*foreach($pretexts as $pretext)
        {
            $this->exclude[] = " ".$pretext." ";
        }*/

        $this->category_page_title_before_words = $this->params->get('category_page_title_before_words', null);
        $this->category_page_title_after_words = $this->params->get('category_page_title_after_words', null);
        $this->category_page_meta_description_before_words = $this->params->get('category_page_meta_description_before_words', null);
        $this->category_page_meta_description_after_words = $this->params->get('category_page_meta_description_after_words', null);


        if ($this->mass_update == "1") {
            return $this->MassUpdate();
        }

    }

    public function onAfterCategoryUpdate(&$element) {
        if (!empty($element->category_id) && $this->update_category == "1") {

            $db = JFactory::getDBO();
            $query = $db->getQuery(true);


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
                if ($this->need_parent_alias == "1") {//и включена опция С родительским алиасом
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
                $db->quoteName('category_canonical') . ' = ' . $db->quote($category_canonical)
            );

            if ($this->categoryMetaKeywords == "1" && empty($element->category_meta_description) && empty($element->category_keywords)) {

                $category_name_text_trim = trim(strip_tags($element->category_name));

                $category_keywords_text = str_replace($this->exclude, ' ', $category_name_text_trim);
                $category_keywords_array = explode(" ", $category_keywords_text);
                $category_keywords = implode(", ", $category_keywords_array);

                $add_key = $category_keywords.', '.$this->MetaKeys;
                $add_meta = $element->category_name.'. '.$this->siteDesc; //.'. '.$this->siteName

                if ($element->category_keywords == "" && $element->category_meta_description == "") {
                    $keywords_category = $add_key;
                    $meta_category = $add_meta;
                } else {
                    $tmp_key = $element->category_keywords;
                    $tmp_new_key = $tmp_key.', '.$add_key;
                    if ($tmp_new_key != $tmp_key) {
                        $keywords_category = $tmp_key;
                    } else {
                        $keywords_category = $tmp_new_key;
                    }

                    $tmp_meta = $element->category_meta_description;
                    $tmp_meta_new = $tmp_meta . ', '.$add_meta;


                    if ($tmp_meta_new != $tmp_meta) {
                        $meta_category = $tmp_meta;
                    } else {
                        $meta_category = $tmp_meta_new;
                    }
                }

                if ($element->category_id == $this->id_main_category) {
                    $category_meta_description = $element->category_name.". ".$this->siteDesc;
                    $category_page_title = $element->category_name.". ".$this->siteName;
                    $keywords_category = $element->category_name.", ".$this->MetaKeys;
                    $category_description  = $category_name_text_trim.". ".$this->siteName;
                } else {
                    $category_page_title_before = $this->category_page_title_before_words." ";
                    $category_page_title_after = " ".$this->category_page_title_after_words;

                    $category_page_meta_description_before = $this->category_page_meta_description_before_words.". ";
                    $category_page_meta_description_after = ". ".$this->category_page_meta_description_after_words;

                    $category_description = $element->category_name;

                    $category_meta_description = $category_page_meta_description_before.$meta_category.$category_page_meta_description_after;
                    $category_page_title = $category_page_title_before.$category_name_text_trim.$category_page_title_after;
                }

                array_push($fields,
                    $db->quoteName('category_keywords') . ' = ' . $db->quote($keywords_category),
                    $db->quoteName('category_meta_description') . ' = ' . $db->quote($category_meta_description),
                    $db->quoteName('category_page_title') . ' = ' . $db->quote($category_page_title)
                );

                if (empty($element->category_description)) {
                    array_push($fields,
                        $db->quoteName('category_description') . ' = ' . $db->quote("<h1 class='uk-h".$this->category_description_heading." uk-text-center'>".$category_description."</h1>")
                    );
                }

            }

            $query
                ->update('#__hikashop_category')
                ->set($fields)
                ->where($db->quoteName('category_id') . ' = ' . $db->quote($element->category_id));
            $db->setQuery($query);
            $db->execute();
            $query->clear();


            if ($this->update_canonical_product_when_change_category == "1") {

                $query
                    ->select('*')
                    ->from($db->quoteName('#__hikashop_product'))
                    ->where($db->quoteName('product_type') . ' = ' . $db->quote('main'))
                    ->where($db->quoteName('product_canonical') . ' LIKE ' . $db->quote('%'.$element->category_canonical.'%'))
                ;
                $db->setQuery($query);
                $all_products = $db->loadObjectList();//Все продукты с канонической ссылкой похожей на каноническую ссылку категории
                $query->clear();

                foreach ($all_products as $all_product) {
                    $old_product_canonical = $element->category_canonical . '/' . $all_product->product_alias; //каноническая ссылка продукта редактируемой категории

                    if($all_product->product_canonical == $old_product_canonical) {// если каноническая ссылка продукта совпадает с редактируемой

                        if (!preg_match("/^[0-9]{1}/", $all_product->product_name)) {
                            /*на основе канонической ссылки категории, получаем алиас товара*/
                            $new_product_canonical = $category_canonical . '/' . JApplicationHelper::stringURLSafe($all_product->product_name, 'ru-RU'); //каноничский продукта
                        } else {
                            /*на основе канонической ссылки категории, получаем алиас товара*/
                            $new_product_canonical = $category_canonical . '/p' . JApplicationHelper::stringURLSafe($all_product->product_name, 'ru-RU'); //каноничский продукта
                        }

                        $fields = array(
                            $db->quoteName('product_canonical') . ' = ' . $db->quote($new_product_canonical)
                        );

                        $query
                            ->update('#__hikashop_product')
                            ->set($fields)
                            ->where($db->quoteName('product_id') . ' = ' . $db->quote($all_product->product_id));
                        $db->setQuery($query);
                        $db->execute();
                        $query->clear();
                    }
                }
            }


            if ($this->update_menu == "1") {
                /*теперь на основе каноничкого адреса категории создадим пункт меню с этой категорией*/

                if ($this->conditions_menu == "params") {
                    $conditions = $db->quoteName('params') . ' LIKE ' . $db->quote('%"category":"' . $element->category_id . '"%');//по категории
                }
                /*(есть такие пункты меню, где проставляли категории вручную и забыли категорию)*/
                if ($this->conditions_menu == "path") {
                    $category_path = mb_substr($element->category_canonical, '1');//$category_path это каноникал категории без начального /
                    $conditions = $db->quoteName('path') . ' = ' . $db->quote($category_path);
                }
                if ($this->conditions_menu == "title") {
                    $conditions = $db->quoteName('title') . ' = ' . $db->quote($element->category_name);//по имени
                }
                if ($this->conditions_menu == "alias") {
                    $conditions = $db->quoteName('alias') . ' = ' . $db->quote($element->category_name);//по алиасу
                }


                $query
                    ->select($db->quoteName(array("id", "path")))
                    ->from($db->quoteName("#__menu"))
                    ->where($conditions)
                    ->where($db->quoteName('menutype') . ' = ' . $db->quote($this->menu))
                    ->where($db->quoteName('published') . ' >=  ' . $db->quote('0'))
                ;

                $db->setQuery($query);
                $menu_datas = $db->loadAssoc();/*Ищем пункт меню с этой категорией */
                $query->clear();

                if($menu_datas == NULL) {
                    $this->app->enqueueMessage("Пункт меню с категорией ".$element->category_name." не найден", "warning");
                    return false;
                }

                if (count($menu_datas) == 2) { //id и path

                    if ($this->show_menu_not_main_category == "0") {
                        $query
                            ->select($db->quoteName('category_canonical'))
                            ->from($db->quoteName('#__hikashop_category'))
                            ->where($db->quoteName('category_id') . ' = ' . $db->quote($this->id_main_category));
                        $db->setQuery($query);
                        $main_category_canonical = $db->loadResult();//каноническая ссылка категории типа каталог
                        $query->clear();

                        $main_category_path = mb_substr($main_category_canonical, '1');//$category_path это каноникал категории без начального /


                        if (strpos($menu_datas["path"], $main_category_path) !== false) // если в path меню содержится каноническая ссылка каталога
                        {
                            $menu_show = "1";
                        } else {
                            $menu_show = "0";

                        }
                    } else {
                        $menu_show = "1";
                    }

                    if ($this->hika_type_menu == "category") { //если выбрана категория, то добавляем для нее параметры
                        $hika_type = '{"hk_category":{"layout_type":"div","columns":"'.$this->cols_cat.'","rows":"'.$this->rows_cat.'","limit":"'.$this->cols_cat*$this->rows_cat.'","div_item_layout_type":"'.$this->div_item_layout_type.'","image_width":"","image_height":"","product_transition_effect":"linear","product_effect_duration":"","pane_height":"","text_center":"-1","show_description_listing":"0","consistencyheight":"1","background_color":"","margin":"","border_visible":"-1","rounded_corners":"-1","ul_class_name":"","ul_display_simplelist":"0","show_image":"0","show_description":"'.$this->show_description.'","category":"'.$element->category_id.'","category_order":"inherit","order_dir":"inherit","random":"-1","filter_type":"0","use_module_name":"0","child_display_type":"inherit","child_limit":"","number_of_products":"-1","only_if_products":"-1"},"hk_product":{"layout_type":"inherit","columns":"'.$this->cols_pr.'","rows":"'.$this->rows_pr.'","limit":"'.$this->cols_pr*$this->rows_pr.'","div_item_layout_type":"inherit","image_width":"","image_height":"","product_transition_effect":"linear","product_effect_duration":"","pane_height":"","text_center":"-1","show_description_listing":"0","consistencyheight":"1","infinite_scroll":"0","background_color":"","margin":"","border_visible":"-1","rounded_corners":"-1","ul_class_name":"","product_order":"inherit","order_dir":"inherit","random":"-1","filter_type":"'.$this->filter_type.'","use_module_name":"0","discounted_only":"0","related_products_from_cart":"0","show_out_of_stock":"-1","link_to_product_page":"-1","show_price":"-1","price_display_type":"inherit","price_with_tax":"3","show_original_price":"-1","show_discount":"3","add_to_cart":"-1","add_to_wishlist":"-1","show_quantity_field":"-1","product_waitlist":"0","show_vote":"-1","display_custom_item_fields":"-1","display_filters":"-1","display_badges":"-1"},"menu-anchor_title":"","menu-anchor_css":"","menu_image":"","menu_image_css":"","menu_text":1,"menu_show":'.$menu_show.',"page_title":"","show_page_heading":"","page_heading":"","pageclass_sfx":"","menu-meta_description":"'.$meta_category.'","menu-meta_keywords":"'.$keywords_category.'","robots":"","secure":0}';
                        $link = 'index.php?option=com_hikashop&view=category&layout=listing';
                    }
                    if ($this->hika_type_menu == "product") {
                        $hika_type = '{"hk_product":{"layout_type":"inherit","columns":"'.$this->cols_pr.'","rows":"'.$this->rows_pr.'","limit":"'.$this->cols_pr*$this->rows_pr.'","div_item_layout_type":"inherit","image_width":"","image_height":"","product_transition_effect":"linear","product_effect_duration":"","pane_height":"","text_center":"-1","show_description_listing":"0","consistencyheight":"1","infinite_scroll":"0","background_color":"","margin":"","border_visible":"-1","rounded_corners":"-1","ul_class_name":"","show_image":"0","show_description":"'.$this->show_description.'","category":"'.$element->category_id.'","product_order":"inherit","order_dir":"inherit","random":"-1","filter_type":"'.$this->filter_type.'","use_module_name":"0","discounted_only":"0","related_products_from_cart":"0","show_out_of_stock":"-1","recently_viewed":"-1","link_to_product_page":"-1","show_price":"-1","price_display_type":"inherit","price_with_tax":"3","show_original_price":"-1","show_discount":"3","add_to_cart":"-1","add_to_wishlist":"-1","show_quantity_field":"-1","show_vote":"-1","display_custom_item_fields":"-1","display_filters":"-1","display_badges":"-1"},"menu-anchor_title":"","menu-anchor_css":"","menu_image":"","menu_image_css":"","menu_text":1,"menu_show":'.$menu_show.',"page_title":"","show_page_heading":"","page_heading":"","pageclass_sfx":"","menu-meta_description":"'.$meta_category.'","menu-meta_keywords":"'.$keywords_category.'","robots":"","secure":0}';
                        $link = 'index.php?option=com_hikashop&view=product&layout=listing';
                    }

                    $menu_id = $menu_datas["id"];

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
                } elseif (count($menu_datas) == 0 || $menu_datas["id"] == "") {
                    return $this->onAfterCategoryCreate($element);
                    //$this->app->enqueueMessage("Соответствующий пункт меню не найден.", "warning");

                } else {
                    $this->app->enqueueMessage("Найдено несколько пунктов меню.", "warning");
                    return false;
                }
            }
        }
    }

    public function onAfterCategoryDelete(&$ids) {

        $menuTable = JTableNested::getInstance('Menu');

        $db = JFactory::getDBO();
        $query = $db->getQuery(true);

        foreach ($ids as $id) {

            $category_path = mb_substr($id->category_canonical, '1');//$category_path это каноникал категории без начального /

            $query
                ->select($db->quoteName(array("id", "title")))
                ->from($db->quoteName("#__menu"))
                ->where($db->quoteName('path') . ' = ' . $db->quote($category_path))
                ->where($db->quoteName('params')  . ' LIKE '.  $db->quote('%"category":"' . $id . '"%'))
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
        if (!empty($element->category_id) && $this->create_category == "1")
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
                if ($this->need_parent_alias == "1") {//и включена опция С родительским алиасом
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
                $db->quoteName('category_canonical') . ' = ' . $db->quote($category_canonical)
            );

            if ($this->categoryMetaKeywords == "1" && empty($element->category_meta_description) && empty($element->category_keywords)) {

                $category_name_text_trim = trim(strip_tags($element->category_name));

                $category_keywords_text = str_replace($this->exclude, ' ', $category_name_text_trim);
                $category_keywords_array = explode(" ", $category_keywords_text);
                $category_keywords = implode(", ", $category_keywords_array);

                $add_key = $category_keywords.', '.$this->MetaKeys;
                $add_meta = $element->category_name.'. '.$this->siteDesc; //.'. '.$this->siteName

                if ($element->category_keywords == "" && $element->category_meta_description == "") {
                    $keywords_category = $add_key;
                    $meta_category = $add_meta;
                } else {
                    $tmp_key = $element->category_keywords;
                    $tmp_new_key = $tmp_key.', '.$add_key;
                    if ($tmp_new_key != $tmp_key) {
                        $keywords_category = $tmp_key;
                    } else {
                        $keywords_category = $tmp_new_key;
                    }

                    $tmp_meta = $element->category_meta_description;
                    $tmp_meta_new = $tmp_meta . ', '.$add_meta;


                    if ($tmp_meta_new != $tmp_meta) {
                        $meta_category = $tmp_meta;
                    } else {
                        $meta_category = $tmp_meta_new;
                    }
                }

                if ($element->category_id == $this->id_main_category) {
                    $category_meta_description = $element->category_name.". ".$this->siteDesc;
                    $category_page_title = $element->category_name.". ".$this->siteName;
                    $keywords_category = $element->category_name.", ".$this->MetaKeys;
                    $category_description  = $category_name_text_trim.". ".$this->siteName;
                } else {
                    $category_page_title_before = $this->category_page_title_before_words." ";
                    $category_page_title_after = " ".$this->category_page_title_after_words;

                    $category_page_meta_description_before = $this->category_page_meta_description_before_words.". ";
                    $category_page_meta_description_after = ". ".$this->category_page_meta_description_after_words;

                    $category_description = $element->category_name;

                    $category_meta_description = $category_page_meta_description_before.$meta_category.$category_page_meta_description_after;
                    $category_page_title = $category_page_title_before.$category_name_text_trim.$category_page_title_after;
                }

                array_push($fields,
                    $db->quoteName('category_keywords') . ' = ' . $db->quote($keywords_category),
                    $db->quoteName('category_meta_description') . ' = ' . $db->quote($category_meta_description),
                    $db->quoteName('category_page_title') . ' = ' . $db->quote($category_page_title)
                );

                if (empty($element->category_description)) {
                    array_push($fields,
                        $db->quoteName('category_description') . ' = ' . $db->quote("<h1 class='uk-h".$this->category_description_heading." uk-text-center'>".$category_description."</h1>")
                    );
                }

            }

            $query
                ->update('#__hikashop_category')
                ->set($fields)
                ->where($db->quoteName('category_id') . ' = ' . $db->quote($element->category_id))
            ;
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


                if (strpos($element->category_canonical, $main_category_canonical) !== false) //если каноническая ссылка типа каталог является частью канонической ссылки категории
                {
                    $menu_show = "1";
                } else {
                    $menu_show = "0";
                }
            } else {
                $menu_show = "1";
            }

            if ($this->hika_type_menu == "category") { //если выбрана категория, то добавляем для нее параметры
                $hika_type = '{"hk_category":{"layout_type":"div","columns":"'.$this->cols_cat.'","rows":"'.$this->rows_cat.'","limit":"'.$this->cols_cat*$this->rows_cat.'","div_item_layout_type":"'.$this->div_item_layout_type.'","image_width":"","image_height":"","product_transition_effect":"linear","product_effect_duration":"","pane_height":"","text_center":"-1","show_description_listing":"0","consistencyheight":"1","background_color":"","margin":"","border_visible":"-1","rounded_corners":"-1","ul_class_name":"","ul_display_simplelist":"0","show_image":"0","show_description":"'.$this->show_description.'","category":"'.$element->category_id.'","category_order":"inherit","order_dir":"inherit","random":"-1","filter_type":"0","use_module_name":"0","child_display_type":"inherit","child_limit":"","number_of_products":"-1","only_if_products":"-1"},"hk_product":{"layout_type":"inherit","columns":"'.$this->cols_pr.'","rows":"'.$this->rows_pr.'","limit":"'.$this->cols_pr*$this->rows_pr.'","div_item_layout_type":"inherit","image_width":"","image_height":"","product_transition_effect":"linear","product_effect_duration":"","pane_height":"","text_center":"-1","show_description_listing":"0","consistencyheight":"1","infinite_scroll":"0","background_color":"","margin":"","border_visible":"-1","rounded_corners":"-1","ul_class_name":"","product_order":"inherit","order_dir":"inherit","random":"-1","filter_type":"'.$this->filter_type.'","use_module_name":"0","discounted_only":"0","related_products_from_cart":"0","show_out_of_stock":"-1","link_to_product_page":"-1","show_price":"-1","price_display_type":"inherit","price_with_tax":"3","show_original_price":"-1","show_discount":"3","add_to_cart":"-1","add_to_wishlist":"-1","show_quantity_field":"-1","product_waitlist":"0","show_vote":"-1","display_custom_item_fields":"-1","display_filters":"-1","display_badges":"-1"},"menu-anchor_title":"","menu-anchor_css":"","menu_image":"","menu_image_css":"","menu_text":1,"menu_show":'.$menu_show.',"page_title":"","show_page_heading":"","page_heading":"","pageclass_sfx":"","menu-meta_description":"'.$meta_category.'","menu-meta_keywords":"'.$keywords_category.'","robots":"","secure":0}';
                $link = 'index.php?option=com_hikashop&view=category&layout=listing';
            }
            if ($this->hika_type_menu == "product") {
                $hika_type = '{"hk_product":{"layout_type":"inherit","columns":"'.$this->cols_pr.'","rows":"'.$this->rows_pr.'","limit":"'.$this->cols_pr*$this->rows_pr.'","div_item_layout_type":"inherit","image_width":"","image_height":"","product_transition_effect":"linear","product_effect_duration":"","pane_height":"","text_center":"-1","show_description_listing":"0","consistencyheight":"1","infinite_scroll":"0","background_color":"","margin":"","border_visible":"-1","rounded_corners":"-1","ul_class_name":"","show_image":"0","show_description":"'.$this->show_description.'","category":"'.$element->category_id.'","product_order":"inherit","order_dir":"inherit","random":"-1","filter_type":"'.$this->filter_type.'","use_module_name":"0","discounted_only":"0","related_products_from_cart":"0","show_out_of_stock":"-1","recently_viewed":"-1","link_to_product_page":"-1","show_price":"-1","price_display_type":"inherit","price_with_tax":"3","show_original_price":"-1","show_discount":"3","add_to_cart":"-1","add_to_wishlist":"-1","show_quantity_field":"-1","show_vote":"-1","display_custom_item_fields":"-1","display_filters":"-1","display_badges":"-1"},"menu-anchor_title":"","menu-anchor_css":"","menu_image":"","menu_image_css":"","menu_text":1,"menu_show":'.$menu_show.',"page_title":"","show_page_heading":"","page_heading":"","pageclass_sfx":"","menu-meta_description":"'.$meta_category.'","menu-meta_keywords":"'.$keywords_category.'","robots":"","secure":0}';
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
                    ->where($db->quoteName('params')  . ' LIKE '.  $db->quote('%"category":"' . $element->category_parent_id . '"%'))
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

            if(!is_array($element->categories)) $element->categories = array($element->categories);
            if(is_array($element->categories))
                $element->categories = array_map('intval', $element->categories);
            else
                $element->categories = array();

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
                    ->select($db->quoteName('category_canonical'))
                    ->from($db->quoteName('#__hikashop_category'))
                    ->where($db->quoteName('category_id') . ' = ' . $db->quote($this->id_main_category));
                $db->setQuery($query);
                $main_category_canonical = $db->loadResult();//каноническая ссылка категории типа каталог
                $query->clear();

                $query
                    ->select($db->quoteName('category_depth'))
                    ->from($db->quoteName('#__hikashop_category'))
                    ->where($db->quoteName('category_id') . ' IN (' . implode(',', $element->categories) . ')')
                    ->where($db->quoteName('category_canonical') . ' LIKE ' . $db->quote('%' . $main_category_canonical . '%'));
                $db->setQuery($query);
                $depths = $db->loadObjectList();
                $maxdepth = max($depths)->category_depth;//наибольшая вложенность среди всех категорий продукта, но в категории типа каталог
                $query->clear();

                $query
                    ->select($db->quoteName('category_canonical'))
                    ->from($db->quoteName('#__hikashop_category'))
                    ->where($db->quoteName('category_depth') . ' = ' . $db->quote($maxdepth))
                    ->where($db->quoteName('category_id') . ' IN (' . implode(',', $element->categories) . ')')
                    ->where($db->quoteName('category_canonical') . ' LIKE ' . $db->quote('%' . $main_category_canonical . '%'));
                $db->setQuery($query);
                $category_canonical = $db->loadResult();//канонический категории (каталог) наибольшей вложенности
                $query->clear();
            }

            if (!preg_match("/^[0-9]{1}/", $element->product_name)) {
                /*на основе канонической ссылки категории, получаем алиас товара*/
                $product_canonical = $category_canonical . '/' . JApplicationHelper::stringURLSafe($element->product_name, 'ru-RU'); //каноничский продукта
            } else {
                /*на основе канонической ссылки категории, получаем алиас товара*/
                $product_canonical = $category_canonical . '/p' . JApplicationHelper::stringURLSafe($element->product_name, 'ru-RU'); //каноничский продукта
            }

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
        if (!empty($element->product_id) && $this->update_product == "1" && $this->app->isClient('administrator')) {

            $db = JFactory::getDBO();
            $query = $db->getQuery(true);

            if(!is_array($element->categories)) $element->categories = array($element->categories);

            if(is_array($element->categories))
                $element->categories = array_map('intval', $element->categories);
            else
                $element->categories = array();


            $query
                ->select($db->quoteName('category_canonical'))
                ->from($db->quoteName('#__hikashop_category'))
                ->where($db->quoteName('category_id') . ' = ' . $db->quote($this->id_main_category));
            $db->setQuery($query);
            $main_category_canonical = $db->loadResult();//каноническая ссылка категории типа каталог
            $query->clear();

            //Если у продукта всего одна категория, то все просто, берем ее
            if (count($element->categories) == "1") {

                $query
                    ->select($db->quoteName('category_canonical'))
                    ->from($db->quoteName('#__hikashop_category'))
                    ->where($db->quoteName('category_id') . ' = ' . $db->quote($element->categories[0]));
                $db->setQuery($query);
                $category_canonical = $db->loadResult();
                $query->clear();

            } else {
                /*иначе сложнее. будем искать максимально глубоко вложенную категорию только внутри категории типа каталог */

                $query
                    ->select($db->quoteName('category_depth'))
                    ->from($db->quoteName('#__hikashop_category'))
                    ->where($db->quoteName('category_id') . ' IN (' . implode(',', $element->categories) . ')')
                    ->where($db->quoteName('category_canonical') . ' LIKE ' . $db->quote('%' . $main_category_canonical . '%'))
                ;
                $db->setQuery($query);
                $depths = $db->loadObjectList();
                $maxdepth = max($depths)->category_depth;//наибольшая вложенность среди всех категорий продукта, но в категории типа каталог
                $query->clear();

                $query
                    ->select($db->quoteName('category_canonical'))
                    ->from($db->quoteName('#__hikashop_category'))
                    ->where($db->quoteName('category_depth') . ' = ' . $db->quote($maxdepth))
                    ->where($db->quoteName('category_id') . ' IN (' . implode(',', $element->categories) . ')')
                    ->where($db->quoteName('category_canonical') . ' LIKE ' . $db->quote('%' . $main_category_canonical . '%'))
                ;
                $db->setQuery($query);
                $category_canonical = $db->loadResult();//канонический категории (каталог) наибольшей вложенности
                $query->clear();
            }

            if (strpos($category_canonical, $main_category_canonical) !== false) {

                if (!preg_match("/^[0-9]{1}/", $element->product_name)) {
                    $product_alias = JApplicationHelper::stringURLSafe($element->product_name, 'ru-RU'); //алиас продукта
                    /*на основе канонической ссылки категории, получаем алиас товара*/
                    $product_canonical = $category_canonical . '/' . JApplicationHelper::stringURLSafe($element->product_name, 'ru-RU'); //каноничский продукта
                } else {
                    $product_alias = "p" . JApplicationHelper::stringURLSafe($element->product_name, 'ru-RU'); //алиас продукта
                    /*на основе канонической ссылки категории, получаем алиас товара*/
                    $product_canonical = $category_canonical . '/p' . JApplicationHelper::stringURLSafe($element->product_name, 'ru-RU'); //каноничский продукта
                }

                if ($this->product_meta_description_and_keywords == "1" && $this->hika_config->get('auto_keywords_and_metadescription_filling') == "0" && $element->product_description == "") { //

                    $max_size_of_metadescription = $this->hika_config->get('max_size_of_metadescription');
                    $product_meta_description_text = trim(strip_tags($element->product_description));
                    $product_meta_description = mb_strimwidth($product_meta_description_text, 0, $max_size_of_metadescription);

                    $keywords_number = $this->hika_config->get('keywords_number');
                    $product_keywords_text_full = trim(strip_tags($element->product_description));
                    $product_keywords_text = str_replace($this->exclude, ' ', $product_keywords_text_full);
                    $product_keywords_array = explode(" ", $product_keywords_text);
                    $words = array_slice($product_keywords_array, 0, $keywords_number, true);
                    $product_keywords = implode(", ", $words);

                    $fields = array(
                        $db->quoteName('product_alias') . ' = ' . $db->quote($product_alias),
                        $db->quoteName('product_canonical') . ' = ' . $db->quote($product_canonical),
                        $db->quoteName('product_meta_description') . ' = ' . $db->quote($product_meta_description),
                        $db->quoteName('product_keywords') . ' = ' . $db->quote($product_keywords)
                    );

                } else {
                    $fields = array(
                        $db->quoteName('product_alias') . ' = ' . $db->quote($product_alias),
                        $db->quoteName('product_canonical') . ' = ' . $db->quote($product_canonical)
                    );
                }

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


    public function MassUpdate() {
        $db = JFactory::getDBO();
        $query = $db->getQuery(true);

        $count_pr_canon = "0";
        $count_cat_alias = "0";
        $count_cat_canonical = "0";
        $count_menu_exists = "0";
        $count_menu_new = "0";
        $count_menu_update = "0";

        //Алиасы и канонические ссылки категорий
        $query
            ->select(array('*'))
            ->from($db->quoteName('#__hikashop_category'))
            ->where($db->quoteName('category_published') . ' = ' . $db->quote('1'))
            ->where($db->quoteName('category_parent_id') . ' > ' . $db->quote('0'))
            ->where($db->quoteName('category_type') . ' = ' . $db->quote('product'))
			->order("category_left ASC");

        $db->setQuery($query);
        $categories = $db->loadObjectList();//Для всех опубликованных категорий, типа продукт, кроме корневой
        $query->clear();

        foreach ($categories as $category) {

            $element = $category;

            $query
                ->select('category_name')
                ->from($db->quoteName('#__hikashop_category'))
                //->where($db->quoteName('category_parent_id') . ' > ' . $db->quote('1'))
                ->where($db->quoteName('category_id') . ' = ' . $db->quote($category->category_parent_id));

            $db->setQuery($query);
            $parent_alias = JApplicationHelper::stringURLSafe($db->loadResult(), 'ru-RU'); //получаем alias родительской категории из имени родительской
            $query->clear();

            $query
                ->select('category_canonical')
                ->from($db->quoteName('#__hikashop_category'))
                ->where($db->quoteName('category_id') . ' = ' . $db->quote($category->category_parent_id));

            $db->setQuery($query);
            $parent_canonical = $db->loadResult();//получаем canonical родительской категории
            $query->clear();

            // Set alias
            if (trim($category->category_alias) == '') { //если пустой алиас категории
                $category_alias = JApplicationHelper::stringURLSafe($category->category_name, 'ru-RU');
            } else {//если уже есть алиас категории
                if ($this->update_category == "1") { //и его надо обновить
                    $category_alias = JApplicationHelper::stringURLSafe($category->category_name, 'ru-RU');
                } else {//оставляем так как есть
                    $category_alias = $category->category_alias;//алиас категории
                }
            }

            if ($parent_alias != "") {
                if ($this->need_parent_alias == "1") {
                    $full_alias = $full_category_alias = $parent_alias . '-' . $category_alias;
                } else {
                    $full_category_alias = $parent_alias . '-' . $category_alias;
                    $full_alias = $category_alias;
                }
            } else {
                $full_alias = $full_category_alias = $category_alias;
            }
            $category_canonical =  $parent_canonical . '/' . $full_alias;//canonical категории = canonical родительской категории + алиас категории

            if ($this->update_category == "1") {//если алиасы и канонические категорий нужно обновить


                $fields = array(
                    $db->quoteName('category_alias') . ' = ' . $db->quote($full_category_alias),
                    $db->quoteName('category_canonical') . ' = ' . $db->quote($category_canonical)
                );

                if ($this->categoryMetaKeywords == "1") {

                    $category_name_text_trim = trim(strip_tags($element->category_name));

                    $category_keywords_text = str_replace($this->exclude, ' ', $category_name_text_trim);
                    $category_keywords_array = explode(" ", $category_keywords_text);
                    $category_keywords = implode(", ", $category_keywords_array);

                    $add_key = $category_keywords.', '.$this->MetaKeys;
                    $add_meta = $element->category_name.'. '.$this->siteDesc; //.'. '.$this->siteName

                    /*$keywords_category = $add_key;
                    $meta_category = $add_meta;*/

                    if ($element->category_keywords == "" && $element->category_meta_description == "") {
                        $keywords_category = $add_key;
                        $meta_category = $add_meta;
                    } else {
                        $tmp_key = $element->category_keywords;
                        $tmp_new_key = $tmp_key.', '.$add_key;
                        if ($tmp_new_key != $tmp_key) {
                            $keywords_category = $tmp_key;
                        } else {
                            $keywords_category = $tmp_new_key;
                        }

                        $tmp_meta = $element->category_meta_description;
                        $tmp_meta_new = $tmp_meta . ', '.$add_meta;


                        if ($tmp_meta_new != $tmp_meta) {
                            $meta_category = $tmp_meta;
                        } else {
                            $meta_category = $tmp_meta_new;
                        }
                    }

                    if ($element->category_id == $this->id_main_category) {
                        $category_meta_description = $element->category_name.". ".$this->siteDesc;
                        $category_page_title = $element->category_name.". ".$this->siteName;
                        $keywords_category = $element->category_name.", ".$this->MetaKeys;
                        $category_description  = $category_name_text_trim.". ".$this->siteName;
                    } else {
                        $category_page_title_before = $this->category_page_title_before_words." ";
                        $category_page_title_after = " ".$this->category_page_title_after_words;

                        $category_page_meta_description_before = $this->category_page_meta_description_before_words.". ";
                        $category_page_meta_description_after = ". ".$this->category_page_meta_description_after_words;

                        $category_description = $element->category_name;

                        $category_meta_description = $category_page_meta_description_before.$meta_category.$category_page_meta_description_after;
                        $category_page_title = $category_page_title_before.$category_name_text_trim.$category_page_title_after;
                    }

                    array_push($fields,
                        $db->quoteName('category_keywords') . ' = ' . $db->quote($keywords_category),
                        $db->quoteName('category_meta_description') . ' = ' . $db->quote($category_meta_description),
                        $db->quoteName('category_page_title') . ' = ' . $db->quote($category_page_title)
                    );

                    if (empty($element->category_description)) {
                        array_push($fields,
                            $db->quoteName('category_description') . ' = ' . $db->quote("<h1 class='uk-h".$this->category_description_heading." uk-text-center'>".$category_description."</h1>")
                        );
                    }

                }

                $query
                    ->update('#__hikashop_category')
                    ->set($fields)
                    ->where($db->quoteName('category_id') . ' = ' . $db->quote($element->category_id))
                ;
                $db->setQuery($query);
                $db->execute();
                $query->clear();
            }

            //создаем пункты меню
            if ($this->hika_type_menu == "category") {
                $hika_type = '{"hk_category":{"layout_type":"div","columns":"'.$this->cols_cat.'","rows":"'.$this->rows_cat.'","limit":"'.$this->cols_cat*$this->rows_cat.'","div_item_layout_type":"'.$this->div_item_layout_type.'","image_width":"","image_height":"","product_transition_effect":"linear","product_effect_duration":"","pane_height":"","text_center":"-1","show_description_listing":"0","consistencyheight":"1","background_color":"","margin":"","border_visible":"-1","rounded_corners":"-1","ul_class_name":"","ul_display_simplelist":"0","show_image":"0","show_description":"'.$this->show_description.'","category":"'.$category->category_id.'","category_order":"inherit","order_dir":"inherit","random":"-1","filter_type":"0","use_module_name":"0","child_display_type":"inherit","child_limit":"","number_of_products":"-1","only_if_products":"-1"},"hk_product":{"layout_type":"inherit","columns":"'.$this->cols_pr.'","rows":"'.$this->rows_pr.'","limit":"'.$this->cols_pr*$this->rows_pr.'","div_item_layout_type":"inherit","image_width":"","image_height":"","product_transition_effect":"linear","product_effect_duration":"","pane_height":"","text_center":"-1","show_description_listing":"0","consistencyheight":"1","infinite_scroll":"0","background_color":"","margin":"","border_visible":"-1","rounded_corners":"-1","ul_class_name":"","product_order":"inherit","order_dir":"inherit","random":"-1","filter_type":"'.$this->filter_type.'","use_module_name":"0","discounted_only":"0","related_products_from_cart":"0","show_out_of_stock":"-1","link_to_product_page":"-1","show_price":"-1","price_display_type":"inherit","price_with_tax":"3","show_original_price":"-1","show_discount":"3","add_to_cart":"-1","add_to_wishlist":"-1","show_quantity_field":"-1","product_waitlist":"0","show_vote":"-1","display_custom_item_fields":"-1","display_filters":"-1","display_badges":"-1"},"menu-anchor_title":"","menu-anchor_css":"","menu_image":"","menu_image_css":"","menu_text":1,"menu_show":1,"page_title":"","show_page_heading":"","page_heading":"","pageclass_sfx":"","menu-meta_description":"","menu-meta_keywords":"","robots":"","secure":0}';
                $link = 'index.php?option=com_hikashop&view=category&layout=listing';
            }
            if ($this->hika_type_menu == "product") {
                $hika_type = '{"hk_product":{"layout_type":"inherit","columns":"'.$this->cols_pr.'","rows":"'.$this->rows_pr.'","limit":"'.$this->cols_pr*$this->rows_pr.'","div_item_layout_type":"inherit","image_width":"","image_height":"","product_transition_effect":"linear","product_effect_duration":"","pane_height":"","text_center":"-1","show_description_listing":"0","consistencyheight":"1","infinite_scroll":"0","background_color":"","margin":"","border_visible":"-1","rounded_corners":"-1","ul_class_name":"","show_image":"0","show_description":"'.$this->show_description.'","category":"'.$category->category_id.'","product_order":"inherit","order_dir":"inherit","random":"-1","filter_type":"'.$this->filter_type.'","use_module_name":"0","discounted_only":"0","related_products_from_cart":"0","show_out_of_stock":"-1","recently_viewed":"-1","link_to_product_page":"-1","show_price":"-1","price_display_type":"inherit","price_with_tax":"3","show_original_price":"-1","show_discount":"3","add_to_cart":"-1","add_to_wishlist":"-1","show_quantity_field":"-1","show_vote":"-1","display_custom_item_fields":"-1","display_filters":"-1","display_badges":"-1"},"menu-anchor_title":"","menu-anchor_css":"","menu_image":"","menu_image_css":"","menu_text":1,"menu_show":1,"page_title":"","show_page_heading":"","page_heading":"","pageclass_sfx":"","menu-meta_description":"","menu-meta_keywords":"","robots":"","secure":0}';
                $link = 'index.php?option=com_hikashop&view=product&layout=listing';
            }

            $category_path = mb_substr($category->category_canonical, '1');//$category_path это каноникал категории без начального /

            //Нужно проверить, есть ли уже такой пункт меню
            $query
                ->select('*')
                ->from($db->quoteName('#__menu'))
                ->where($db->quoteName('params')  . " LIKE ".  "'%\"category\":\"" . (int)$category->category_id . "\"%'")
                //->where($db->quoteName('path') . ' = ' . $db->quote($category_path))
                ->where($db->quoteName('published') . ' >= ' . $db->quote('0'))
                ->where($db->quoteName('menutype') . ' = ' . $db->quote($this->menu));


            $db->setQuery($query);
            $db->execute();
            $num_menus = $db->getNumRows();
            $data_menu = $db->loadObjectList();

            $query->clear();

            if ($num_menus == "0") {//если нет пункта меню с нужной категорией
                // также если не совпадают патх или имя с алиасом
                if ($data_menu[0]->path != $category_path OR ($data_menu[0]->title != $category->category_name AND $data_menu[0]->alias != $category->category_alias)) {

                    //создаем соответствующий пункт меню
                    $menuTable = JTableNested::getInstance('Menu');

                    $menuData = array(
                        'menutype'      => $this->menu,
                        'title'         => $category->category_name,
                        'alias'         => $full_alias,
                        'type'          => 'component',
                        'component_id'  => $this->component_id,
                        'link'          => $link,
                        'language'      => '*',
                        'published'     => 1,
                        'params'        => $hika_type
                    );

                    if ($category->category_parent_id == 1) {
                        $parent_id = "1"; /* если это лежит в главной категории */
                    } else {
                        $query = $db->getQuery(true);
                        $query
                            ->select($db->quoteName("id"))
                            ->from($db->quoteName("#__menu"))
                            ->where($db->quoteName('params') . " LIKE '%\"category\":\"" . (int)$category->category_parent_id . "\"%'")
                            ->where($db->quoteName('published') . ' >= ' . $db->quote('0'))
                            ->where($db->quoteName('menutype') . ' = ' . $db->quote($this->menu));

                        $db->setQuery($query);
                        $data = $db->loadAssoc();
                        $parent_id = $data["id"];
					}

                    $menuTable->setLocation((int)$parent_id, 'last-child');

                    if (!$menuTable->bind($menuData)) {
                        throw new RuntimeException($menuTable->getError());
                    }

                    if (!$menuTable->check()) {
                        throw new RuntimeException($menuTable->getError());
                    }

                    if (!$menuTable->store()) {
                        throw new RuntimeException($menuTable->getError());
                    } else {
                        $count_menu_new++;

                    }
                }

            } else {// если пункт меню с нужной категорией существует и его нужно обновить
                $count_menu_exists++;

                if ($this->update_menu == "1") {

                    $fields_menu = array(
                        $db->quoteName('title') . ' = ' . $db->quote($category->category_name),
                        $db->quoteName('alias') . ' = ' . $db->quote($full_alias),
                        $db->quoteName('params') . ' = ' . $db->quote($hika_type),
                        $db->quoteName('link') . ' = ' . $db->quote($link)
                    );
                    $query
                        ->update('#__menu')
                        ->set($fields_menu)
                        ->where($db->quoteName('id') . ' = ' . $db->quote($data_menu[0]->id))
                        ->where($db->quoteName('published') . ' >= ' . $db->quote('0'))
                        ->where($db->quoteName('menutype') . ' = ' . $db->quote($this->menu));
                    $db->setQuery($query);
                    if ($db->execute()) {
                        $count_menu_update++;
                    }
                    $query->clear();

                    $menuTable = JTableNested::getInstance('Menu');
                    // Rebuild the tree path.
                    if (!$menuTable->rebuildPath($data_menu[0]->id)) {
                        $this->setError($menuTable->getError());
                        return false;
                    }
                }
            }

        }

//На основе канонических ссылок категорий, создаем продукт каноникал и записываем его
		$query = $db->getQuery(true);
        $query
            ->select('*')
            ->from($db->quoteName('#__hikashop_product'))
            ->where($db->quoteName('product_type') . ' = ' . $db->quote('main'));
        $db->setQuery($query);
        $product_datas = $db->loadObjectList();//all main products
        $query->clear();

        foreach ($product_datas as $product_data) {

            $query
                ->select('category_id')
                ->from($db->quoteName('#__hikashop_product_category'))
                ->where($db->quoteName('product_id') . ' = ' . $db->quote($product_data->product_id));

            $db->setQuery($query);
            $cat_ids = $db->loadObjectList();//categories for product
            $query->clear();

            if (count($cat_ids) == 1) { // 1 category for product

                $query
                    ->select('category_canonical')
                    ->from($db->quoteName('#__hikashop_category'))
                    ->where($db->quoteName('category_id') . ' = ' . $db->quote($cat_ids[0]->category_id));
                $db->setQuery($query);
                $category_canonical = $db->loadObjectList();//categories canonical
                $query->clear();

                if (!preg_match("/^[0-9]{1}/", $product_data->product_name)) {
                    /*на основе канонической ссылки категории, получаем алиас товара*/
                    $product_canonical = $category_canonical[0]->category_canonical . '/' . JApplicationHelper::stringURLSafe($product_data->product_name, 'ru-RU'); //каноничский продукта
                } else {
                    /*на основе канонической ссылки категории, получаем алиас товара*/
                    $product_canonical = $category_canonical[0]->category_canonical . '/p' . JApplicationHelper::stringURLSafe($product_data->product_name, 'ru-RU'); //каноничский продукта
                }

                $fields_product = array(
                    $db->quoteName('product_canonical') . ' = ' . $db->quote($product_canonical),
                );

                $query
                    ->update('#__hikashop_product')
                    ->set($fields_product)
                    ->where($db->quoteName('product_id') . ' = ' . $db->quote($product_data->product_id));
                $db->setQuery($query);
                if ($db->execute()) {
                    $count_pr_canon++;
                }
                $query->clear();

            } else {

                /*иначе сложнее. будем искать максимально глубоко вложенную категорию только внутри категории типа каталог */

                $query
                    ->select($db->quoteName('category_canonical'))
                    ->from($db->quoteName('#__hikashop_category'))
                    ->where($db->quoteName('category_id') . ' = ' . $db->quote($this->id_main_category));
                $db->setQuery($query);
                $main_category_canonical  = $db->loadResult();//каноническая ссылка категории типа каталог
                $query->clear();

                $query
                    ->select($db->quoteName('category_id'))
                    ->from($db->quoteName('#__hikashop_product_category'))
                    ->where($db->quoteName('product_id') . ' = ' . $db->quote($product_data->product_id));
                $db->setQuery($query);
                $product_categories = $db->loadAssocList();//все категории продукта
                $query->clear();

                foreach ($product_categories as $rt=>$product_category) {
                    foreach ($product_category as $tr=>$product_cat) {

                        $query
                            ->select('*')
                            ->from($db->quoteName('#__hikashop_category'))
                            ->where($db->quoteName('category_id') . ' = ' . $db->quote($product_cat))
                            ->where($db->quoteName('category_canonical') . ' LIKE ' . $db->quote('%' . $main_category_canonical . '%'));
                        $db->setQuery($query);
                        $cat_datas = $db->loadRowList();//наибольшая вложенность среди всех категорий продукта, но в категории типа каталог
                        $query->clear();

                    }
                }

                $query
                    ->select($db->quoteName('category_canonical'))
                    ->from($db->quoteName('#__hikashop_category'))
                    ->where($db->quoteName('category_id') . ' = ' . $db->quote($cat_datas[0][0]));
                $db->setQuery($query);
                $category_canonical  = $db->loadResult();//канонический категории (каталог) наибольшей вложенности
                $query->clear();


                if (!preg_match("/^[0-9]{1}/", $product_data->product_name)) {
                    /*на основе канонической ссылки категории, получаем алиас товара*/
                    $product_canonical = $category_canonical . '/' . JApplicationHelper::stringURLSafe($product_data->product_name, 'ru-RU'); //каноничский продукта
                } else {
                    /*на основе канонической ссылки категории, получаем алиас товара*/
                    $product_canonical = $category_canonical . '/p' . JApplicationHelper::stringURLSafe($product_data->product_name, 'ru-RU'); //каноничский продукта
                }

                $fields = array(
                    $db->quoteName('product_canonical') . ' = ' . $db->quote($product_canonical)
                );

                $query
                    ->update('#__hikashop_product')
                    ->set($fields)
                    ->where($db->quoteName('product_id') . ' = ' . $db->quote($product_data->product_id));
                $db->setQuery($query);
                $db->execute();
                $query->clear();

            }

            if ($this->product_meta_description_and_keywords == "1" && $this->hika_config->get('auto_keywords_and_metadescription_filling') == "0" && $product_data->product_description != "") { //
                $product_description_text = ltrim(strip_tags($product_data->product_description));

                $max_size_of_metadescription = $this->hika_config->get('max_size_of_metadescription');
                $product_meta_description = mb_strimwidth($product_description_text, 0, $max_size_of_metadescription);

                $keywords_number = $this->hika_config->get('keywords_number');
                $product_keywords_text = str_replace($this->exclude, ' ', $product_description_text);
                $product_keywords_array = explode(" ", $product_keywords_text);
                $words = array_slice($product_keywords_array, 0, $keywords_number, true);
                $product_keywords = implode(", ", $words);

                $fields_product_meta_key = array(
                    $db->quoteName('product_meta_description') . ' = ' . $db->quote($product_meta_description),
                    $db->quoteName('product_keywords') . ' = ' . $db->quote($product_keywords)
                );

                $query
                    ->update('#__hikashop_product')
                    ->set($fields_product_meta_key)
                    ->where($db->quoteName('product_id') . ' = ' . $db->quote($product_data->product_id));
                $db->setQuery($query);
                $db->execute();
                $query->clear();

            }

        }

        die();
    }

}
