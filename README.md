# FlexPress orm component

## Install with Pimple
- This example creates a document model, notice that we pass in the global post object, the ORM system will then copy over all the properties from the post object, including the ID, which is very important.
- - Please note that this example is done as a service and would return the same object for each call, you should use the factory method in pimple if you want to create a new object each time.
```
$pimple["documentModel"] = function () {
  return new DocumentModel($GLOBALS['post']);
};

```
## Creating a concreate class
- You must extend the AbstractORM class, which has no abstract methods to implement so don't worry that you are not getting prompted to do so.
- The way the ORM system works is docblocks on the properties, for example in this very simple class:
```
class DocumentModel extends AbstractORM {

  /**
   * @type acf
   */
  protected $type;

}
```
- The property must be none public, as the ORM system require you to use the getters and setters that are implemeted via magic methods to access the property. The system also uses lazy loading so it will only
- The system also uses smart changes so it will only update the values you have changed.
- By defining it as a acf the ORM system will try to load the value using the get_field function, typically you want to prefix your properties in the database, the ORM system allows you to do this, there is a property called $prefixes, which all default to fp_ but you can change these like this:
```
public __construct($post = false) {

  parent::__construct($post);
  
  $this->prefixes['acf'] = 'document_'

}
```
This would then let to ORM system know that when you load the value for $type you want it to load it using get_field with the field_key of document_type.

## Property types
There are currently three types a property can be:
- acf (Advanced Custom Field)
- meta (Post Meta)
- tax (Taxonomy)

As shown above you set these in the docblock for the property, here are examples of the differnt types:
```
  /**
   * @type acf
   */
  protected $type;
  
    /**
   * @type meta
   */
  protected $lastUpdated;
  
    /**
   * @type tax
   */
  protected $category;

```
You will notice that the lastUpdated property is in camelcase, the ORM system will convert this for you, if we set the prefixes['meta'] = "document_"; then what we would get is document_last_updated.
 
## Accessing data
As previously mentioned the ORM system uses PHP magic methods to create methods for you to interact with the properties.
There are three methods:
- set<propertyName>($value) - Sets the property to the provided value.
- the<propertyName>() - This outputs the property as a string.
- get<propertyName>() - This returns the property as a string.
- theFormatted<PropertyName>($arg) - Currently only works with dates, formates the date for the given format and echos the return.
- getFormatted<PropertyName>($arg) - Currently only works with dates, formates the date for the given format and returns the result.

### Basic usage

To output the property lastUpdated you would call it like this:
```
$document->theLastUpdated();
```
And then to set that property you would do something like this:
```
$document->setLastUpdated("20140808");
```
And then if you wanted to get that property you would use get instead of the:
```
$lastUpdated = $document->getLastUpdated();
```

### Special features

When using the the<propertyName>() method with a taxonomy it returns the first ones name for example
```
$document->theCategory();
```
would return the first terms name but if you wanted to return all the terms as objects then you would do pass true in
```
$categories = $document->theCategory(true);
```

## Saving data
To save the changes you simply call ->persist() on the model ,e.g. following on from the example above:
```
$document = $pimple["documentModel"];
$document->setLastUpdated("20140808");
$document->persist();
```

## Public methods
- getFeaturedImage($image_size, $attrs = array()) - Gets the featured image for the given imagesize and arrays
- theFeaturedImage($image_size, $attrs = array()) - Outputs the featured image for the given imagesize and arrays
- getOriginalPostObject() - Returns the original post object that was passed to the model
- instanceByID($id) - Static method to get a instance of the model for a given post id
- delete($force_delete = true) - Uses wp_delete_post to delete the model / post
- persist() - saves the model

# Protected methods
- loadPropertyValue($propertyName, $type, $overrideKey = false) - Used to load the property value
- getDocblockAttrs($attr) - Used to get the doc block attributes
- getUnderscoredEquivalent($value) - Gets the underscore equivalent of the camelcase value
