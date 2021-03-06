<?php

namespace CG_TMP_PROJECTNAME\App\Model;

/**
 * Description of DbField
 * Generated by CoreGenerator
 * 
 * @author kminekmatej, CG_TMP_DATE
 */
class DbField {

    private $property;
    private $field;
    private $changeable;

    public function __construct($property, $field, $changeable = TRUE) {
        $this->property = $property;
        $this->field = $field;
        $this->changeable = $changeable;
    }

    public function getProperty() {
        return $this->property;
    }

    public function getField() {
        return $this->field;
    }

    public function setProperty($property) {
        $this->property = $property;
        return $this;
    }

    public function setField($field) {
        $this->field = $field;
        return $this;
    }

    public function isChangeable() {
        return $this->changeable;
    }

    public function setChangeable($changeable) {
        $this->changeable = $changeable;
        return $this;
    }

}
