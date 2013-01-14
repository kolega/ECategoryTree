<?php

class ECategoryTreeBehaviour extends CActiveRecordBehavior
{
    const ROOT_NODE_ID = 1;
    const ROOT_NODE_NAME = 'Корневой раздел';

    const REPEAT_STRING = '&nbsp;&nbsp;&nbsp;&nbsp;';

    public $categoryLink = '';

    public $idField = 'id';
    public $parentIdField = 'parent';
    public $nameField = 'title';
    public $orderField = 'order';
    public $urlNameField = 'name';

    public function init()
    {
        if( empty($this->categoryLink) )
        {
            throw new CException("Please set 'categoryLink' field.");
        }
    }

    public function getCategoryLink($id, $name = '')
    {
    	if( empty($name) )
    	{
		    return array(
		        $this->categoryLink,
		        'id' => $id,
		    );
		}
		
		return array(
	        $this->categoryLink,
	        'name' => $name,
	    );
    }

    public function getCacheName()
    {
        return 'cacheTree' . get_class($this->owner);
    }

    /*
     * получить список категорий для ListBox
     */
    public function getCategoryListByTree($buildTree = true)
    {
        $list = array();
        $tree = $this->getCache();
        if( !is_array($tree) )
        {
            return $list;
        }
        $this->buildCategoryList(
            array(
                'children' => $tree,
                'name' => CategoryTreeBehaviour::ROOT_NODE_NAME,
                'id' => CategoryTreeBehaviour::ROOT_NODE_ID,
            ),
            $list,
            0,
            $buildTree
        );
        return $list;
    }

    private function buildCategoryList($parentNode, &$listNode, $level, $buildTree)
    {
        if( count($parentNode['children']) > 0 )
        {
            $name = str_repeat(CategoryTreeBehaviour::REPEAT_STRING, $level) . ' ' . $parentNode['name'];
            $id = $parentNode['id'];
            if( $buildTree )
            {
                $listNode[$name] = array();
            }
            else
            {
                $listNode[$id] = $name;
            }
            foreach($parentNode['children'] as $child)
            {
                if( $buildTree )
                {
                    $this->buildCategoryList($child, $listNode[$name], $level+1, $buildTree);
                }
                else
                {
                    $this->buildCategoryList($child, $listNode, $level+1, $buildTree);
                }
            }
        }
        else
        {
            $name = str_repeat(CategoryTreeBehaviour::REPEAT_STRING, $level) . ' ' . $parentNode['name'];
            $listNode[$parentNode['id']] = $name;
        }
    }

    /*
     * получить дерево в виде массива (например, для CArrayDataProvider)
     */
    public function getCategoryByArrayTree($returnRoot = false)
    {
        $list = array();
        $tree = $this->getCache();
        if( !is_array($tree) )
        {
            return $list;
        }
        $this->buildCategoryByArrayTree(
            array(
                'children' => $tree,
                'name' => CategoryTreeBehaviour::ROOT_NODE_NAME,
                'id' => CategoryTreeBehaviour::ROOT_NODE_ID,
            ),
            $list,
            0
        );
        if( $returnRoot == false )
        {
            unset($list[0]);
        }
        return $list;
    }

    private function buildCategoryByArrayTree($parentNode, &$listNode, $level)
    {
        $name = $parentNode['name'];
		$order = $parentNode['order'];
        if( count($parentNode['children']) > 0 )
        {
            $name = "<b>{$name}</b>";
        }
        $name = str_repeat(CategoryTreeBehaviour::REPEAT_STRING, $level) . ' ' . $name;
        $id = $parentNode['id'];
        array_push(
            $listNode,
            array(
                'id' => $id,
                'name' => $name,
				'order' => $order,
            )
        );
        foreach($parentNode['children'] as $child)
        {
            $this->buildCategoryByArrayTree($child, $listNode, $level+1);
        }
    }

    /*
     * построить дерево категорий
     */
    public function buildCategoryTree($returnRootNode = false)
    {
        $idField = $this->idField;
        $parentIdField = $this->parentIdField;
        $nameField = $this->nameField;
        $urlNameField = $this->urlNameField;
		$orderField = $this->orderField;

        $criteria = new CDbCriteria();
        $criteria->order = "`{$orderField}` ASC";

        $categoryList = $this->owner->findAll($criteria);

        $allCategories = array();

        foreach($categoryList as $category)
        {
            $allCategories[$category->$idField] = array(
                'id' => $category->$idField,
                'name' => $category->$nameField,
                'urlName' => $category->$urlNameField,
                'parent' => $category->$parentIdField,
				'order' => $category->$orderField,
            );
        }

        $tree = array();
        $this->_buildCategoryTree($allCategories, CategoryTreeBehaviour::ROOT_NODE_ID, $tree);
        
        if( $returnRootNode == false )
        {
            reset($tree);
            $tree = $tree[key($tree)]['children'];
        }

        Yii::app()->cache->set($this->getCacheName(), $tree);

        return $tree;
    }

    private function _buildCategoryTree($allCategories, $node, &$result)
    {
        $result[] = array(
            'id' => $allCategories[$node]['id'],
            'children' => array(),
            'name' => $allCategories[$node]['name'],
			'order' => $allCategories[$node]['order'],
			'urlName' => $allCategories[$node]['urlName'],
        );

        if( $node == CategoryTreeBehaviour::ROOT_NODE_ID )
        {
            $result[count($result)-1]['expanded'] = true;
        }
        else
        {
            $result[count($result)-1]['expanded'] = false;
        }

        if( $this->hasChildCategories($allCategories, $node) )
        {
            $result[count($result)-1]['text'] = "<span>{$allCategories[$node]['name']}</span>";
        }
        else
        {
            $result[count($result)-1]['text'] = CHtml::link(
                $allCategories[$node]['name'],
                $this->getCategoryLink($allCategories[$node]['id'], $allCategories[$node]['urlName'])
            );
        }

        foreach($allCategories as $childItem)
        {
            if( $childItem['parent'] == $node && $childItem['id'] != CategoryTreeBehaviour::ROOT_NODE_ID)
            {
                $this->_buildCategoryTree($allCategories, $childItem['id'], $result[count($result)-1]['children']);
            }
        }
    }

    private function hasChildCategories($allCategories, $node)
    {
        foreach($allCategories as $category)
        {
            if( $category['parent'] == $node )
            {
                return true;
            }
        }
        return false;
    }

    public function clearCache()
    {
        Yii::app()->cache->delete($this->getCacheName());
    }

    public function getCache()
    {
        return Yii::app()->cache->get($this->getCacheName());
    }

    public function getChildrenCategory($id = self::ROOT_NODE_ID)
    {
        return $this->owner->findAll(
            $this->parentIdField . ' = :id',
            array(
                ':id' => $id,
            )
        );
    }

    /*
     * удаление children категорий
     */
    public function beforeDelete($event)
    {
		parent::beforeDelete($event);
		
        $id = $this->idField;
        $parentId = $this->parentIdField;

        $criteria = new CDbCriteria();
        $criteria->condition = "{$parentId} = :id";
        $criteria->params = array(
            ':id' => $this->owner->$id,
        );

        $childs = $this->owner->findAll($criteria);
        foreach($childs as $category)
        {
            $category->delete();
        }

        return true;
    }

    public function afterDelete($event)
    {
		parent::afterDelete($event);
        $this->buildCategoryTree();
        return true;
    }

    public function afterSave($event)
    {
		parent::afterSave($event);
        $this->buildCategoryTree();
        return true;
    }
}

?>
