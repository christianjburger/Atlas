<?php
namespace Atlas\Model;

use Atlas\Exception as Exception;

abstract class Mapper
{
    protected $_db;

    protected $_alias;

    protected $_table;
    
    protected $_map = array();
    
    protected $_mapReadOnly = array();
    
    public static $mask = 'base36';
    
    abstract public function createObject($row);

    abstract public function createCollection($rows);

    public function getAlias()
    {
        return $this->_alias;
    }

    public function getTable()
    {
        return $this->_table;
    }
    
    /**
     * @return Zend_Db_Adapter_Pdo_Mysql $db
     */
    public function db()
    {
        if ($this->_db === null) {
            $this->_db = \Atlas\Db::getInstance()->getAdapter();
        }
    
        return $this->_db;
    }

    /**
     * @return Zend_Db_Select
     */
    public function select()
    {
        return $this->db()->select();
    }

    public function save($entity)
    {
        return $this->_save($this->_table, $this->_extract($entity), $entity);
    }
    
    public function fetch($id)
    {
        return $this->_fetch($this->_table, $id);
    }

    public function delete($model)
    {
        return $this->_delete($this->_table, $model);
    }
    
    /**
     *
     * @param string $table
     * @param int $primarykey
     * @param string $pkColumn
     * @return Atom_Model
     */
    protected function _fetch($table, $primarykey, $pkColumn = 'id')
    {
        if (empty($primarykey)) {
            throw new Exception('Cannot fetch record from ' . $table . '. No primary key provided');
        }
        
        $select = $this->db()->select()
            ->from($table)
            ->where($pkColumn . ' = ?', $primarykey);
        
        return $this->createObject($select->query()->fetch());
    }
    
    /**
     * @param string $table
     * @param array $data
     * @param Atom_Model_Entity $model
     * @param string $pkField
     */
    protected function _save($table, $data, $model, $pkField = 'id')
    {
        if ($model->getId() !== null)
        {
            $this->db()->update($table, $data, $pkField . ' = ' . $this->db()->quote($model->getId()));
            $model->notifyObservers('change');
        } else {
            $this->db()->insert($table, $data);
            $id = $this->db()->lastInsertId();
            $model->setId($id);
            $model->notifyObservers('create');
        }
    
        return $model->getId();
    }
    
    /**
     * @param string $table
     * @param Atom_Model_Entity $model
     * @param string $pkField
     */
    protected function _delete($table, $model, $pkField = 'id')
    {
        $this->db()->delete($table,$pkField . ' = ' . $this->db()->quote($model->getId()));
        $model->notifyObservers('delete');
    }
    
    /**
     * @param array $row
     * @return array
     */
    protected function _populate($row)
    {
        if (!is_array($row)) {
            return array();
        }

        $properties = array();
        
        foreach($this->_map as $property => $field) {
            if (!array_key_exists($field, $row)) {
                continue;
            }
            $properties[$property] = $row[$field];
        }
        
        return $properties;
    }
    
    /**
     * @param Atom_Model_Entity $model
     * @return array
     */
    protected function _extract($model)
    {
        $properties = $model->toArray();
        $data = array();
        
        foreach(array_flip($this->_map) as $field => $property) {
            if ($field == 'id' || in_array($field, $this->_mapReadOnly)) {
                continue;
            }
            $data[$field] = $properties[$property];
        }
        return $data;
    }
}