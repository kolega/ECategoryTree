Category Tree Extension
========================

How to Use?
-----

1. Import all class from extension directory:

```php
Yii::import('ext.ECateoryTree.*');
```

2. Create or change old category class:

```php
class Category extends ICategoryTree {
...
}
```

3. Set behavior and extension options:

```php
public function behaviors()
{
    return array(
        'categorytree' => array(
            'class' => 'ECategoryTreeBehaviour',

            'categoryLink' => 'front/category',
        ),
    );
}
```
