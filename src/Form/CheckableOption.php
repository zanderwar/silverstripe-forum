<?php

namespace SilverStripe\Forum\Form;

use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\CompositeField;

/**
 * Class CheckableOption
 * 
 * @package forum
 */
class CheckableOption extends CompositeField
{
    protected $childField, $checkbox;

    /**
     * CheckableOption constructor.
     *
     * @param null   $checkName
     * @param        $childField
     * @param string $value
     * @param bool   $readonly
     */
    public function __construct($checkName, $childField, $value = "", $readonly = false)
    {
        $this->name = $checkName;
        $this->checkbox = new CheckboxField($checkName, "", $value);
        if ($readonly) {
            $this->checkbox->setDisabled(true);
        }

        $this->childField = $childField;

        $children = new FieldList(
            $this->childField,
            $this->checkbox
        );

        parent::__construct($children);
    }

    /**
     * @param array $properties
     *
     * @return \SilverStripe\ORM\FieldType\DBHTMLText
     */
    public function FieldHolder($properties = array())
    {
        return FormField::FieldHolder($properties);
    }

    /**
     * @return mixed
     */
    public function Message()
    {
        return $this->childField->Message();
    }

    /**
     * @return mixed
     */
    public function MessageType()
    {
        return $this->childField->MessageType();
    }

    /**
     * @return mixed
     */
    public function Title()
    {
        return $this->childField->Title();
    }

    /**
     * @param array $properties
     *
     * @return string
     */
    public function Field($properties = array())
    {
        return $this->childField->Field() . ' ' . $this->checkbox->Field();
    }
}
