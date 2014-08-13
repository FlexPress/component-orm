<?php

namespace FlexPress\Components\ORM;

use ReflectionClass;
use ReflectionProperty;

abstract class AbstractORM
{

    // has to be public to match WP_Post definition and we can't extend it
    public $ID;
    public $post_author;
    public $post_date;
    public $post_date_gmt;
    public $post_content;
    public $post_title;
    public $post_excerpt;
    public $post_status;
    public $comment_status;
    public $ping_status;
    public $post_password;
    public $post_name;
    public $to_ping;
    public $pinged;
    public $post_modified;
    public $post_modified_gmt;
    public $post_content_filtered;
    public $post_parent;
    public $guid;
    public $menu_order;
    public $post_type;
    public $post_mime_type;
    public $comment_count;
    public $filter;
    public $format_content;

    /**
     * @type tax
     * @key category
     */
    protected $category;

    /**
     * Stores the original post object passed to us
     *
     * @var bool
     */
    private $postObject;

    /**
     *
     * The permalink for this model
     * @var bool|string
     */
    protected $permalink;

    /**
     * An array of prefixes used when getting a value,
     * e.g. allows you to omit fp_ from your property names so that
     * protected $image becomes fp_image
     *
     * @var array
     *
     */
    protected $prefixes = array("acf" => "fp_", "tax" => "fp_", "meta" => "fp_");

    /**
     * Stores what values have been changed,
     * then we don't save over properties not
     * modified.
     *
     * @var array
     */
    private $valuesChanged = array();

    // ==================
    // ! CONSTRUCTORS
    // ==================

    /**
     * Optionally pass a post object and all of its properties
     * will be consumed by this model
     *
     * @param bool $post
     */
    public function __construct($post = false)
    {

        if ($post) {

            $this->postObject = $post;

            // grab all attrs from the post object
            foreach (get_object_vars($post) as $k => $v) {
                $this->$k = $v;
            }

            $this->permalink = get_permalink($this->ID);

        }

    }

    /**
     *
     * For a given image size and optional attributes returns the featured image
     *
     * @param $image_size
     * @param array $attrs
     * @return mixed|void
     * @author Tim Perry
     *
     */
    public function getFeaturedImage($image_size, $attrs = array())
    {

        $attrs = array_merge(array("class" => ""), $attrs);
        return get_the_post_thumbnail($this->ID, $image_size, $attrs);

    }

    /**
     *
     * Alias for getFeaturedImage but it echos it screen
     *
     * @param $image_size
     * @param $attrs
     * @return mixed|void
     * @author Tim Perry
     *
     */
    public function theFeaturedImage($image_size, $attrs = array())
    {

        echo $this->getFeaturedImage($image_size, $attrs);

    }

    /**
     *
     * Used to get the origonal post object passed to the models constructor
     *
     * @return bool
     * @author Tim Perry
     *
     */
    public function getOriginalPostObject()
    {

        if (isset($this->postObject)) {
            return $this->postObject;
        }

        return false;

    }

    /**
     *
     * Static method to get a an instance of this model using its post_id
     *
     * @param $id
     * @return mixed
     * @author Tim Perry
     *
     */
    public static function instanceByID($id)
    {

        $className = get_called_class();
        return new $className(get_post($id));

    }

    // ==================
    // ! METHODS
    // ==================

    /**
     *
     * Deletes the object from the database
     *
     * @author Tim Perry
     *
     */
    public function delete($force_delete = true)
    {

        if (isset($this->ID)) {
            wp_delete_post($this->ID, $force_delete);
        }

    }

    /**
     *
     * Magic method got getters and setters allow you to do:
     *
     * set_ - sets the value
     * the_ - outputs the value
     * get_ - returns the value
     * get_formatted returns the formatted value
     * the_formatted outputs the formatted value
     *
     * @param $name
     * @param $args
     * @return array|bool|mixed|string
     * @author Tim Perry
     *
     */
    public function __call($name, $args)
    {

        $matches = null;

        // convert camelcase to underscores
        preg_match_all(
            '!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!',
            $name,
            $matches
        );

        $action = $matches[0][0];

        $format = ($matches[0][1] == "formatted");

        $offset = ($format) ? 2 : 1;
        $propertyName = lcfirst(implode("", array_slice($matches[0], $offset)));

        if ($action == "get" || $action == "the") {

            $class = new ReflectionClass($this);

            $property = $class->getProperty($propertyName);
            $property->setAccessible(true);

            $docBlockAttrs = $this->getDocblockAttrs($property);

            $type = $overrideKey = false;

            if (isset($docBlockAttrs["type"])) {
                $type = strtolower($docBlockAttrs["type"]);
            }

            if (isset($docBlockAttrs["key"])) {
                $overrideKey = strtolower($docBlockAttrs["key"]);
            }

            if (!isset($this->$propertyName)) {
                $this->loadPropertyValue($propertyName, $type, $overrideKey);
            }

            $value = $this->$propertyName;

            // when type is a taxonomy default to returning a single term unless true is passed
            if ((is_array($value) && ($type == "tax" || $type == "taxonomy"))) {

                // args not set or args set but is false
                if (!isset($args[0]) || $args[0] == false) {

                    $value = current($value);

                }

            }

            // For post author, return the name
            if ($propertyName == "post_author") {
                $value = get_the_author_meta("display_name", $this->post_author);
            }

            // format currently only works with dates
            if ($format && isset($args[0]) && is_string($args[0])) {
                $value = date($args[0], strtotime($value));
            }

            if ($action == "get") {
                return $value;
            }

            if ($action == "the"
                && ($type == "tax" || $type == "taxonomy")
                && is_object($value)
                && property_exists($value, "name")
            ) {
                echo $value->name;
            }

            if (is_string($value)) {
                echo $value;
            }

        } elseif ($action == "set") {

            // add the property name to the list of changed values
            $this->valuesChanged[] = $propertyName;
            $this->$propertyName = $args[0];

        }

    }

    /**
     *
     * Loads the value for a property for a given propertyName, type and override key
     *
     * @param $propertyName
     * @param $type
     * @param bool $overrideKey
     * @author Tim Perry
     *
     */
    protected function loadPropertyValue($propertyName, $type, $overrideKey = false)
    {

        $propertyNameUnderscored = $this->getUnderscoredEquivalent($propertyName);

        switch ($type) {

            case "acf":

                if (!$key = $overrideKey) {
                    $key = get_field_reference($this->prefixes["acf"] . $propertyNameUnderscored, $this->ID);
                }

                $this->$propertyName = get_field($key, $this->ID);
                break;

            case "tax":

                if (!$key = $overrideKey) {
                    $key = $this->prefixes["tax"] . $propertyNameUnderscored;
                }

                if ($value = wp_get_object_terms($this->ID, $key)) {

                    if (!is_wp_error($value)) {
                        $this->$propertyName = $value;
                    }

                }
                break;

            case "meta":

                if (!$key = $overrideKey) {
                    $key = $this->prefixes["meta"] . $propertyNameUnderscored;
                }

                $this->$propertyName = get_post_meta($this->ID, $key, true);

                break;
        }

    }

    /**
     *
     * Gets all the doc block attributes and combines them
     * in a key value array for the given attribute
     *
     * @param $attr
     * @return array
     * @author Tim Perry
     *
     */
    protected function getDocblockAttrs($attr)
    {

        if (preg_match_all('/@(\w+) (.+)\r?\n/m', $attr->getDocComment(), $matches)) {

            // combine into key => value array
            $attrs = array_combine($matches[1], $matches[2]);

            return $attrs;

        }

    }

    /**
     *
     * Returns the underscore equivalent for the given
     * camel case value
     *
     * @param $value
     * @return string
     * @author Tim Perry
     *
     */
    protected function getUnderscoredEquivalent($value)
    {

        $matches = null;

        // convert camelcase to underscores
        preg_match_all(
            '!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!',
            $value,
            $matches
        );

        return strtolower(implode("_", $matches[0]));

    }

    /**
     *
     * Saves the changed values to the database
     *
     * @return int|WP_Error
     * @author Tim Perry
     *
     */
    public function persist()
    {

        if (isset($this->ID)) {

            $WPPost = new ReflectionClass("WP_Post");
            $WPPostAttrs = $WPPost->getProperties(ReflectionProperty::IS_PUBLIC);

            $args = array();

            foreach ($WPPostAttrs as $attr) {

                $attr_name = $attr->getName();
                $args[$attr_name] = $this->$attr_name;

            }

            wp_update_post($args);

        } else {

            $args = array(

                'post_status' => 'publish',
                'post_type' => $this->post_type,
                'post_author' => 1,
                'post_title' => $this->post_title,
                'post_date' => $this->post_date,
                'post_content' => $this->post_content

            );

            $this->ID = wp_insert_post($args);

        }

        $class = new ReflectionClass($this);

        foreach ($class->getProperties() as $attr) {

            $attr->setAccessible(true);
            $name = $attr->getName();
            $value = $attr->getValue($this);

            // only update changed values
            if (in_array($name, $this->valuesChanged)) {

                $doc_block_attrs = $this->getDocblockAttrs($attr);

                if ($type = strtolower($doc_block_attrs["type"])) {

                    $override_key = $doc_block_attrs["key"];

                    $name = $this->getUnderscoredEquivalent($name);

                    switch ($type) {

                        case "acf":

                            if (!$key = $override_key) {
                                $key = $this->prefixes["acf"] . $name;
                            }

                            update_field($key, $value, $this->ID);
                            break;

                        case "tax":

                            if (!$key = $override_key) {
                                $key = $this->prefixes["tax"] . $name;
                            }

                            if (is_array($value)) {

                                foreach ($value as $k => $v) {

                                    // if the value is not an int (id) or string(slug), remove it
                                    if (!is_int($v) && !is_string($v)) {
                                        unset($value[$k]);
                                    }

                                }

                            }

                            // for a single int, we need to save the terms and then load back the full tax
                            wp_set_object_terms($this->ID, $value, $key);
                            $this->loadPropertyValue($name, $type, $key);
                            break;

                        case "meta":

                            if (!$key = $override_key) {
                                $key = $this->prefixes["meta"] . $name;
                            }

                            update_post_meta($this->ID, $key, $value);
                            break;

                    }

                }

            }

        }

        return $this->ID;

    }
}
