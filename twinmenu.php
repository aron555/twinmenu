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


	public function plgHikashopTwinmenu(&$subject, $config)
	{
		parent::__construct($subject, $config);
		$this->menu = $this->params->get('menu', null);
		$this->component_id = JComponentHelper::getComponent('com_hikashop')->id;
	}

	public function onAfterCategoryUpdate(&$element) {

        $query=$this->db->getQuery(true);/*Ищем пункт меню с этой категорией */
        $query
            ->select($this->db->quoteName("id"))
            ->from($this->db->quoteName("#__menu"))
            ->where($this->db->quoteName('params')  . ' LIKE '.  $this->db->quote('%\"category\":\"' . $element->category_id . '\"%'))
            ->where($this->db->quoteName('published')  . ' >=  '. $this->db->quote('0'));

	    $this->db->setQuery($query);
	    $data = $this->db->loadAssoc();
	    if (count($data) > 0)
	    {
		    $menu_id = $data["id"];

		    $menuTable = JTableNested::getInstance('Menu');
		    $menuTable->load($menu_id);

		    $menuData = array(
			    'title' => $element->category_name,
		    );


		    if (!$menuTable->save($menuData))
		    {
			    $this->app->enqueueMessage("Ошибка!!","error");
			    return false;
		    }
	    } else {
		    $this->app->enqueueMessage("Соответствующий пункт меню не найден.", "warning");

	    }
    }

	public function onAfterCategoryDelete(&$ids) {

		$menuTable = JTableNested::getInstance('Menu');
		foreach ($ids as $id) {
			$query=$this->db->getQuery(true);
			$query->select("id, title");
			$query->from("#__menu");
			$query->where("params LIKE ('%\"category\":\"".$id."\"%')");
			$query->where("`published`>=0");

			try
			{
				$this->db->setQuery($query);
				$data = $this->db->loadAssoc();
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
            $update = $this->params->get('update', null);
            $need_parent_alias = $this->params->get('need_parent_alias', null);

            //получаем alias родительской категории из имени родительской
            $db = JFactory::getDBO();
            $query = $db->getQuery(true);
            $query
                ->select('category_name')
                ->from($db->quoteName('#__hikashop_category'))
                ->where($db->quoteName('category_parent_id') . ' > ' . $db->quote('1'))
                ->where($db->quoteName('category_id') . ' = ' . $db->quote($element->category_parent_id));

            $db->setQuery($query);
            $parent_alias = JApplicationHelper::stringURLSafe($db->loadResult(), 'ru-RU');
            $query->clear();


            $db = JFactory::getDBO(); //получаем canonical родительской категории
            $query = $db->getQuery(true);
            $query
                ->select('category_canonical')
                ->from($db->quoteName('#__hikashop_category'))
                ->where($db->quoteName('category_id') . ' = ' . $db->quote($element->category_parent_id));

            $db->setQuery($query);
            $parent_canonical = $db->loadResult();
            $query->clear();

            // Set alias
            if (trim($element->category_alias) == '') { //если пустой алиас категории
                $category_alias = JApplicationHelper::stringURLSafe($element->category_name, 'ru-RU');
            } else {//если уже есть алиас категории
                if ($update == "update") { //и его надо обновить
                    $category_alias = JApplicationHelper::stringURLSafe($element->category_name, 'ru-RU');
                } else {//оставляем так как есть
                    $category_alias = $element->category_alias;//алиас категории
                }
            }

            if ($parent_alias != "") {
                if ($need_parent_alias == "need_parent_alias") {
                    $full_alias = $full_category_alias = $parent_alias . '-' . $category_alias;
                } else {
                    $full_category_alias = $parent_alias . '-' . $category_alias;
                    $full_alias = $category_alias;
                }
            } else {
                $full_alias = $full_category_alias = $category_alias;
            }

            $category_canonical =  $parent_canonical . '/' . $full_alias;//canonical категории = canonical родительской категории + алиас категории

            if ($element->category_alias == "") {//если алиасы категорий пустые
                $fields = array(
                    $db->quoteName('category_alias') . ' = ' . $db->quote($full_category_alias)
                );
            }

            if ($element->category_canonical == "") {//если канонические категорий пустые
                $fields = array(
                    $db->quoteName('category_canonical') . ' = ' . $db->quote($category_canonical)
                );
            }

            if ($element->category_canonical == "" && $element->category_alias == "") {//если алиасы и канонические категорий пустые или их нужно обновить
                $fields = array(
                    $db->quoteName('category_alias') . ' = ' . $db->quote($full_category_alias),
                    $db->quoteName('category_canonical') . ' = ' . $db->quote($category_canonical)
                );
            }

            if ($update == "update") {//если алиасы и канонические категорий нужно обновить
                $fields = array(
                    $db->quoteName('category_alias') . ' = ' . $db->quote($full_category_alias),
                    $db->quoteName('category_canonical') . ' = ' . $db->quote($category_canonical),
                    //$db->quoteName('category_keywords') . ' = ' . $db->quote($parent_alias.', '.$category_alias.', '.$category->category_name),
                    //$db->quoteName('category_meta_description') . ' = ' . $db->quote($parent_alias.' '.$category_alias.' '.$category->category_name)
                );
            }

            $db = JFactory::getDBO();
            $query = $db->getQuery(true);
            $query
                ->update('#__hikashop_category')
                ->set($fields)
                ->where($db->quoteName('category_id') . ' = ' . $db->quote($element->category_id));
            $db->setQuery($query);
            $db->execute();
            $query->clear();

            $hika_type_menu = $this->params->get('hika_type_menu', null);
            $cols_pr = $this->params->get('cols_pr', null);
            $rows_pr = $this->params->get('rows_pr', null);

            if ($hika_type_menu == "category") { //если выбрана категория, то добавляем для нее параметры
                $div_item_layout_type = "title"; // вывод категории, обращение?, если выбрана категория
                $cols_cat = $this->params->get('cols_cat', null);
                $rows_cat = $this->params->get('rows_cat', null);
                $show_description = $this->params->get('show_description', null);
                $hika_type = '{"hk_category":{"layout_type":"div","columns":"'.$cols_cat.'","rows":"'.$rows_cat.'","limit":"'.$cols_cat*$rows_cat.'","div_item_layout_type":"'.$div_item_layout_type.'","image_width":"","image_height":"","product_transition_effect":"linear","product_effect_duration":"","pane_height":"","text_center":"-1","show_description_listing":"0","consistencyheight":"1","background_color":"","margin":"","border_visible":"-1","rounded_corners":"-1","ul_class_name":"","ul_display_simplelist":"0","show_image":"0","show_description":".$show_description.","category":"'.$element->category_id.'","category_order":"inherit","order_dir":"inherit","random":"-1","filter_type":"0","use_module_name":"0","child_display_type":"inherit","child_limit":"","number_of_products":"-1","only_if_products":"-1"},"hk_product":{"layout_type":"inherit","columns":"'.$cols_pr.'","rows":"'.$rows_pr.'","limit":"'.$cols_pr*$rows_pr.'","div_item_layout_type":"inherit","image_width":"","image_height":"","product_transition_effect":"linear","product_effect_duration":"","pane_height":"","text_center":"-1","show_description_listing":"0","consistencyheight":"1","infinite_scroll":"0","background_color":"","margin":"","border_visible":"-1","rounded_corners":"-1","ul_class_name":"","product_order":"inherit","order_dir":"inherit","random":"-1","filter_type":"2","use_module_name":"0","discounted_only":"0","related_products_from_cart":"0","show_out_of_stock":"-1","link_to_product_page":"-1","show_price":"-1","price_display_type":"inherit","price_with_tax":"3","show_original_price":"-1","show_discount":"3","add_to_cart":"-1","add_to_wishlist":"-1","show_quantity_field":"-1","product_waitlist":"0","show_vote":"-1","display_custom_item_fields":"-1","display_filters":"-1","display_badges":"-1"},"menu-anchor_title":"","menu-anchor_css":"","menu_image":"","menu_image_css":"","menu_text":1,"menu_show":1,"page_title":"","show_page_heading":"","page_heading":"","pageclass_sfx":"","menu-meta_description":"","menu-meta_keywords":"","robots":"","secure":0}';
                $link = 'index.php?option=com_hikashop&view=category&layout=listing';
            }
            if ($hika_type_menu == "product") {
                $hika_type = '{"hk_product":{"layout_type":"inherit","columns":"'.$cols_pr.'","rows":"'.$rows_pr.'","limit":"'.$cols_pr*$rows_pr.',"div_item_layout_type":"inherit","image_width":"","image_height":"","product_transition_effect":"linear","product_effect_duration":"","pane_height":"","text_center":"-1","show_description_listing":"0","consistencyheight":"1","infinite_scroll":"0","background_color":"","margin":"","border_visible":"-1","rounded_corners":"-1","ul_class_name":"","show_image":"0","show_description":"0","category":"'.$element->category_id.'","product_order":"inherit","order_dir":"inherit","random":"-1","filter_type":"0","use_module_name":"0","discounted_only":"0","related_products_from_cart":"0","show_out_of_stock":"-1","recently_viewed":"-1","link_to_product_page":"-1","show_price":"-1","price_display_type":"inherit","price_with_tax":"3","show_original_price":"-1","show_discount":"3","add_to_cart":"-1","add_to_wishlist":"-1","show_quantity_field":"-1","show_vote":"-1","display_custom_item_fields":"-1","display_filters":"-1","display_badges":"-1"},"menu-anchor_title":"","menu-anchor_css":"","menu_image":"","menu_image_css":"","menu_text":1,"menu_show":1,"page_title":"","show_page_heading":"","page_heading":"","pageclass_sfx":"","menu-meta_description":"","menu-meta_keywords":"","robots":"","secure":0}';
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
			    'published'    => 1,
			    'params'       => $hika_type
		    );

		    if ($element->category_parent_id == 2)
		    {
			    $parent_id = 1; /* если это лежит  в главной категории */
		    }
		    else
		    {
			    $query = $this->db->getQuery(true);
			    $query->select("id");
			    $query->from("#__menu");
			    $query->where("params LIKE ('%\"category\":\"" . $element->category_parent_id . "\"%')");
			    $query->where("`published`>=0");

			    try
			    {
				    $this->db->setQuery($query);
				    $data = $this->db->loadAssoc();

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
        if (count($element->categories) == 1) {
            $db = JFactory::getDBO();

            $query = $db->getQuery(true);//Ищем у продукта максимальное вложение категории
            $query
                ->select($db->quoteName('#__hikashop_category.category_canonical'))
                ->from($db->quoteName('#__hikashop_category'))
                ->where($db->quoteName('category_id') . ' = ' . $db->quote($element->categories[0]))
            ;
            $db->setQuery($query);
            $category_canonical  = $db->loadResult();
            $query->clear();
            $product_canonical = $category_canonical.'/'.$element->product_alias;

            $fields = array(
                $db->quoteName('product_canonical') . ' = ' . $db->quote($product_canonical)
            );

            $db = JFactory::getDBO();
            $query = $db->getQuery(true);
            $query
                ->update('#__hikashop_product')
                ->set($fields)
                ->where($db->quoteName('product_id') . ' = ' . $db->quote($element->product_id));
            $db->setQuery($query);
            $db->execute();
            $query->clear();

        } else {
		
		$id_main_category = $this->params->get('id_main_category', null);

            $db = JFactory::getDBO();
            $query = $db->getQuery(true);
            $query
                ->select($db->quoteName('#__hikashop_category.category_canonical'))
                ->from($db->quoteName('#__hikashop_category'))
                ->where($db->quoteName('category_id') . ' = ' . $db->quote($id_main_category))
            ;
            $db->setQuery($query);
            $main_category_canonical  = $db->loadResult();//каталог
            $query->clear();

            $db = JFactory::getDBO();
            $query = $db->getQuery(true);//
            $query
                ->select($db->quoteName('#__hikashop_category.category_depth'))
                ->from($db->quoteName('#__hikashop_category'))
                ->where($db->quoteName('category_id') . ' IN (' . implode(',', $element->categories) . ')')
                ->where($db->quoteName('category_canonical') . ' LIKE ' . $db->quote('%' . $main_category_canonical . '%'))
            ;
            $db->setQuery($query);
            $depths  = $db->loadObjectList();
            $maxdepth = max($depths)->category_depth;
            $query->clear();

            $db = JFactory::getDBO();
            $query = $db->getQuery(true);//
            $query
                ->select($db->quoteName('#__hikashop_category.category_canonical'))
                ->from($db->quoteName('#__hikashop_category'))
                ->where($db->quoteName('category_depth') . ' = ' . $db->quote($maxdepth))
                ->where($db->quoteName('category_id') . ' IN (' . implode(',', $element->categories) . ')')
                ->where($db->quoteName('category_canonical') . ' LIKE ' . $db->quote('%' . $main_category_canonical . '%'))
            ;
            $db->setQuery($query);
            $category_canonical  = $db->loadResult();
            $query->clear();

            $product_canonical = $category_canonical.'/'.$element->product_alias;

            $fields = array(
                $db->quoteName('product_canonical') . ' = ' . $db->quote($product_canonical)
            );

            $db = JFactory::getDBO();
            $query = $db->getQuery(true);
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
