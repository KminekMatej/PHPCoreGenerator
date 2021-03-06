<?php

namespace CG_TMP_PROJECTNAME\App\Services;

use CG_TMP_PROJECTNAME\App\Model\CG_TMP_Object;

/**
 * Description of CG_TMP_Service
 * Generated by CoreGenerator
 * 
 * @author kminekmatej, CG_TMP_DATE
 */
class CG_TMP_Service extends TableService {

    const TABLE = "CG_TMP_Table";
    const ID_FIELD = "CG_TMP_ID_FIELD";
    
    protected function getScheme() {
        $fields = [];
        /*CG_TMP_DBFIELD_PRIMARY:START*/$fields["CG_TMP_FIELD"] = new DbField("CG_TMP_PROPERTY", "CG_TMP_FIELD", FALSE);
        /*CG_TMP_DBFIELD_PRIMARY:END*/
        /*CG_TMP_DBFIELD_NORMAL:START*/$fields["CG_TMP_FIELD"] = new DbField("CG_TMP_PROPERTY", "CG_TMP_FIELD");
        /*CG_TMP_DBFIELD_NORMAL:END*/
        return $fields;
    }

    protected function getTable() {
        return self::TABLE;
    }

    /** @return CG_TMP_Object */
    protected function map($result) {
        if (is_null($result) || $result === FALSE)
            return NULL;
        $obj = new CG_TMP_Object();
        $scheme = $this->getScheme();

        foreach ($scheme as $dbField) {
            $prop = $dbField->getProperty();
            $field = $dbField->getField();
            if ($result->$field === NULL)
                continue;
            $setter = "set" . ucfirst($prop);

            switch ($prop) {
                case "created":
                    $obj->$setter($this->getDateTime($result->$field));
                    break;
                default:
                    $obj->$setter($result->$field);
                    break;
            }
        }

        return $obj;
    }

    /** @return CG_TMP_Object */
    public function find($id) {
        return parent::findId($id);
    }

    /** @return string */
    protected function getIdField() {
        return self::ID_FIELD;
    }

    /** @return CG_TMP_Object[] */
    public function findAll($order = NULL, $limit = NULL, $offset = NULL) {
        return parent::findAllId($order, $limit, $offset);
    }

}
