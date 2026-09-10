<?php

/** Stores explicit opt-in decisions per course and per child resource. */
class ilIliasEventBridgeCourseTrackingRepository
{
    public const COURSE_TABLE = 'evnt_evhk_ileb_ccfg';
    public const RESOURCE_TABLE = 'evnt_evhk_ileb_rcfg';

    /** @var mixed */
    private $db;

    public function __construct()
    {
        if (isset($GLOBALS['DIC']) && is_object($GLOBALS['DIC']) && method_exists($GLOBALS['DIC'], 'database')) {
            $this->db = $GLOBALS['DIC']->database();
        } elseif (isset($GLOBALS['ilDB'])) {
            $this->db = $GLOBALS['ilDB'];
        } else {
            throw new RuntimeException('ILIAS database object not available.');
        }
    }

    public function courseTableExists(): bool
    {
        return method_exists($this->db, 'tableExists') && $this->db->tableExists(self::COURSE_TABLE);
    }

    public function resourceTableExists(): bool
    {
        return method_exists($this->db, 'tableExists') && $this->db->tableExists(self::RESOURCE_TABLE);
    }

    public function tablesExist(): bool
    {
        return $this->courseTableExists() && $this->resourceTableExists();
    }

    /** @return array<string,mixed> */
    public function getCourseConfig(int $courseRefId): array
    {
        if ($courseRefId <= 0 || !$this->courseTableExists()) {
            return [];
        }
        $set = $this->db->query(
            'SELECT course_ref_id, course_obj_id, enabled, created_at, updated_at, updated_by'
            . ' FROM ' . self::COURSE_TABLE
            . ' WHERE course_ref_id = ' . $this->db->quote($courseRefId, 'integer')
        );
        $row = $this->db->fetchAssoc($set);
        return is_array($row) ? $row : [];
    }

    public function isCourseConfigured(int $courseRefId): bool
    {
        return $this->getCourseConfig($courseRefId) !== [];
    }

    public function isCourseEnabled(int $courseRefId): bool
    {
        $row = $this->getCourseConfig($courseRefId);
        return $row !== [] && (int) ($row['enabled'] ?? 0) === 1;
    }

    public function setCourseEnabled(int $courseRefId, int $courseObjId, bool $enabled, int $updatedBy = 0): void
    {
        if ($courseRefId <= 0 || !$this->courseTableExists()) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        if ($this->isCourseConfigured($courseRefId)) {
            $this->db->manipulate(
                'UPDATE ' . self::COURSE_TABLE
                . ' SET course_obj_id = ' . $this->db->quote(max(0, $courseObjId), 'integer')
                . ', enabled = ' . $this->db->quote($enabled ? 1 : 0, 'integer')
                . ', updated_at = ' . $this->db->quote($now, 'text')
                . ', updated_by = ' . $this->db->quote(max(0, $updatedBy), 'integer')
                . ' WHERE course_ref_id = ' . $this->db->quote($courseRefId, 'integer')
            );
            return;
        }
        $this->db->insert(self::COURSE_TABLE, [
            'course_ref_id' => ['integer', $courseRefId],
            'course_obj_id' => ['integer', max(0, $courseObjId)],
            'enabled' => ['integer', $enabled ? 1 : 0],
            'created_at' => ['text', $now],
            'updated_at' => ['text', $now],
            'updated_by' => ['integer', max(0, $updatedBy)],
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    public function findResourceConfigs(int $courseRefId): array
    {
        if ($courseRefId <= 0 || !$this->resourceTableExists()) {
            return [];
        }
        $set = $this->db->query(
            'SELECT course_ref_id, ref_id, obj_id, obj_type, enabled, created_at, updated_at, updated_by'
            . ' FROM ' . self::RESOURCE_TABLE
            . ' WHERE course_ref_id = ' . $this->db->quote($courseRefId, 'integer')
            . ' ORDER BY obj_type ASC, ref_id ASC'
        );
        $rows = [];
        while ($row = $this->db->fetchAssoc($set)) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    /** @return array<string,mixed> */
    public function getResourceConfig(int $courseRefId, int $refId): array
    {
        if ($courseRefId <= 0 || $refId <= 0 || !$this->resourceTableExists()) {
            return [];
        }
        $set = $this->db->query(
            'SELECT course_ref_id, ref_id, obj_id, obj_type, enabled, created_at, updated_at, updated_by'
            . ' FROM ' . self::RESOURCE_TABLE
            . ' WHERE course_ref_id = ' . $this->db->quote($courseRefId, 'integer')
            . ' AND ref_id = ' . $this->db->quote($refId, 'integer')
        );
        $row = $this->db->fetchAssoc($set);
        return is_array($row) ? $row : [];
    }

    public function isResourceConfigured(int $courseRefId, int $refId): bool
    {
        return $this->getResourceConfig($courseRefId, $refId) !== [];
    }

    public function isResourceEnabled(int $courseRefId, int $refId): bool
    {
        $row = $this->getResourceConfig($courseRefId, $refId);
        return $row !== [] && (int) ($row['enabled'] ?? 0) === 1;
    }

    public function setResourceEnabled(
        int $courseRefId,
        int $refId,
        int $objId,
        string $objType,
        bool $enabled,
        int $updatedBy = 0
    ): void {
        if ($courseRefId <= 0 || $refId <= 0 || !$this->resourceTableExists()) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $objType = substr(trim($objType), 0, 64);
        if ($this->isResourceConfigured($courseRefId, $refId)) {
            $this->db->manipulate(
                'UPDATE ' . self::RESOURCE_TABLE
                . ' SET obj_id = ' . $this->db->quote(max(0, $objId), 'integer')
                . ', obj_type = ' . $this->db->quote($objType, 'text')
                . ', enabled = ' . $this->db->quote($enabled ? 1 : 0, 'integer')
                . ', updated_at = ' . $this->db->quote($now, 'text')
                . ', updated_by = ' . $this->db->quote(max(0, $updatedBy), 'integer')
                . ' WHERE course_ref_id = ' . $this->db->quote($courseRefId, 'integer')
                . ' AND ref_id = ' . $this->db->quote($refId, 'integer')
            );
            return;
        }
        $this->db->insert(self::RESOURCE_TABLE, [
            'course_ref_id' => ['integer', $courseRefId],
            'ref_id' => ['integer', $refId],
            'obj_id' => ['integer', max(0, $objId)],
            'obj_type' => ['text', $objType],
            'enabled' => ['integer', $enabled ? 1 : 0],
            'created_at' => ['text', $now],
            'updated_at' => ['text', $now],
            'updated_by' => ['integer', max(0, $updatedBy)],
        ]);
    }

    public function deleteCourseConfig(int $courseRefId): void
    {
        if ($courseRefId <= 0) {
            return;
        }
        if ($this->resourceTableExists()) {
            $this->db->manipulate(
                'DELETE FROM ' . self::RESOURCE_TABLE
                . ' WHERE course_ref_id = ' . $this->db->quote($courseRefId, 'integer')
            );
        }
        if ($this->courseTableExists()) {
            $this->db->manipulate(
                'DELETE FROM ' . self::COURSE_TABLE
                . ' WHERE course_ref_id = ' . $this->db->quote($courseRefId, 'integer')
            );
        }
    }
}
