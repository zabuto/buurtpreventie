<?php

namespace Zabuto\Bundle\BuurtpreventieBundle\Model;

use Doctrine\DBAL\Connection;

class UserGroup
{
    /**
     * @var Connection $conn
     */
    private $conn;
    
    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }
    
    /**
     * Find group ID by name
     *
     * @param string $name
     * @return integer|false
     */
    public function findGroupId($name)
    {
        $sql = 'SELECT id FROM zabuto_usergroup WHERE name = :name';
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('name', $name);
        $stmt->execute();
        return $stmt->fetchColumn();
    }
    
    /**
     * Find group members by group ID
     *
     * @param integer $groupId
     * @return array
     */
    public function findGroupMembers($groupId)
    {
        $sql = 'SELECT g.user_id AS id, u.real_name AS naam
                FROM zabuto_user_usergroup g, zabuto_user u 
                WHERE g.group_id = :group_id AND u.id = g.user_id';
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('group_id', $groupId);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}