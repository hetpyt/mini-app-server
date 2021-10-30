<?php

class DBQueryBuilder {

    protected $suppOperations = ['SELECT', 'INSERT', 'DELETE', 'UPDATE'];
    protected $fullFieldName = true;
    protected $usePseudonymes = false;

    protected $query = '';

    public function __construct(protected $table = null, protected $operation = "SELECT", protected $fields = null, protected $conditions = null) {
    }

    public function __destruct() {

    }

    public function setTable($table)
    {
        $this->table = $table;
    }

    public function setOperation($operation) {
        if (in_array($operation, $this->suppOperations, true)) {
            $this->operation = $operation;
        }
    }

    public function setFields($fields) {
        $this->fields = $fields;
    }

    public function setFieldPseudonymes($pseudonymes) {
        if (is_array($pseudonymes)) {
            $this->fieldPseudonymes = $pseudonymes;
            $this->usePseudonymes = true;
        } else {
            $this->fieldPseudonymes = null;
            $this->usePseudonymes = false;
        }
    }

    public function setConditions() {

    }

    public function execute() {
        return $this->buildQuery();
    }

    public function buildQuery() {
        $this->query = '';
        if ($this->operation && $this->table) {
            switch ($this->operation) {
                case 'SELECT':
                    $this->query = $this->buildSelect();
                    break;
                case 'INSERT':

                    break;
                case 'DELETE':

                    break;
                case 'UPDATE':

                    break;
            }
        } else {
            throw new Exception('sql operation or (and) table not set');
        }
        return $this->query;
    }

    protected function buildSelect() {
        $query = 'SELECT ' . $this->fieldsToString();
        $query .= ' FROM ' . $this->backquote($this->table);

        return $query . ';';
    }

    protected function buildConditions() {
        if ($this->conditions) {
            if (is_array($this->conditions)) {
                
            }
        }
    }

    protected function fieldsToString() {
        $res = '*';
        if (is_array($this->fields)) {
            $res = $this->arrayToFieldsString($this->fields);

        } elseif (is_string($this->fields) && $this->fields) {
            $fileds_arr = explode(',', $this->fields);
            $res = $this->arrayToFieldsString($fileds_arr);

        }
        return $res;
    }

    protected function arrayToFieldsString($fields) {
        $res = '';
        foreach ($fields as $field) {
            $field = trim($field);
            $res .= ($res ? ", " : "") . ($this->fullFieldName ? "`$this->table`." : "") . "`$field`" 
                . ($this->usePseudonymes && $field != "*" ? " AS " . $this->getPseudonym($field) : "");
        }
        return $res;
    }

    protected function getPseudonym($field) {
        if ($this->usePseudonymes) {
            if (is_array($this->fieldPseudonymes) && array_key_exists($field, $this->fieldPseudonymes)) {
                return $this->fieldPseudonymes[$field];
            } else {
                return $field;
            }
        } else {
            return "";
        }
    }

    protected function backquote(string $str) {
        if (!$str) {
            return $str;
        }
        return "`" . $str . "`";
    }
}


?>