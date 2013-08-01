<?php

namespace Kunstmaan\TranslatorBundle\Service\Migrations;

class MigrationsService
{

    /**
     * Repository for Translations
     * @var \Kunstmaan\TranslatorBundle\Repository\TranslationRepository
     */
    private $translationRepository;

    private $entityManager;

    public function getDiffSqlArray()
    {
        $sql = array();
        $sql[] = $this->getNewTranslationSql();
        $sql = array_merge($sql, $this->getUpdatedTranslationSqlArray());

        return $sql;
    }

    // XXX : broken
    // FIXME: needs refactoring
    public function getUpdatedTranslationSqlArray()
    {
        $ignoreFields = array('id');
        $uniqueKeys = array('domain', 'locale', 'keyword');

        $translations = $this->translationRepository->findBy(array('flag' => \Kunstmaan\TranslatorBundle\Entity\Translation::FLAG_UPDATED));

        if (count($translations) <= 0) {
            return array();
        }

        $fieldNames =  $this->entityManager->getClassMetadata('\Kunstmaan\TranslatorBundle\Entity\Translation')->getFieldNames();

        $tableName = $this->entityManager->getClassMetadata('\Kunstmaan\TranslatorBundle\Entity\Translation')->getTableName();
        $tableName = $this->entityManager->getConnection()->quoteIdentifier($tableName);

        $fieldNames = array_diff($fieldNames, $ignoreFields, $primaryKeys);

        $sql = array();

        foreach ($translations as $translation) {

            $updateValues = array();
            $whereValues = array();

            foreach ($fieldNames as $fieldName) {
                $value = $translation->{'get'.$fieldName}();

                if ($value instanceof \DateTime) {
                    $value = $value->format('Y-m-d H:i:s');
                }

                $updateValues[] = $this->entityManager->getConnection()->quoteIdentifier($fieldName) . ' = ' . $this->entityManager->getConnection()->quote($value);
            }

            foreach ($primaryKeys as $primaryKey) {
                $value = $translation->{'get'.$primaryKey}();

                if ($value instanceof \DateTime) {
                    $value = $value->format('Y-m-d H:i:s');
                }

                $whereValues[] = $this->entityManager->getConnection()->quoteIdentifier($primaryKey) . ' = ' . $this->entityManager->getConnection()->quote($value);
            }

            $sql[] = sprintf('UPDATE %s SET %s WHERE %s', $tableName, implode(",", $updateValues), implode(' AND ', $whereValues));

        }

        return $sql;

    }

    /**
     * Build an sql insert into query by the paramters provided
     * @param  ORM\Entity $entities        Result array with all entities to create an insert for
     * @param  string     $entityClassName Class of the specified entity (same as entities)
     * @param  array      $ignoreFields    fields not to use in the insert query
     * @return string     an insert sql query, of no result nul
     */
    public function buildInsertSql($entities, $entityClassName, $ignoreFields = array())
    {
        if (count($entities) <= 0) {
            return null;
        }

        $fieldNames = array_merge(
                $this->entityManager->getClassMetadata($entityClassName)->getFieldNames(),
                $this->entityManager->getClassMetadata($entityClassName)->getAssociationNames()
                );

        $tableName = $this->entityManager->getClassMetadata($entityClassName)->getTableName();
        $tableName = $this->entityManager->getConnection()->quoteIdentifier($tableName);
        $fieldNames = array_diff($fieldNames, $ignoreFields);

        $values = array();

        foreach ($entities as $entity) {

            $insertValues = array();

            foreach ($fieldNames as $fieldName) {
                $value = $entity->{'get'.$fieldName}();

                if ($value instanceof \DateTime) {
                    $value = $value->format('Y-m-d H:i:s');
                }

                $insertValues[] = $this->entityManager->getConnection()->quote($value);
            }

            $values[] = '(' . implode(',', $insertValues) . ')';
        }

        $fieldNames = array_map(function($fieldName) { return $this->entityManager->getConnection()->quoteIdentifier($fieldName);}, $fieldNames);

        $sql = sprintf('INSERT INTO %s (%s) VALUES %s', $tableName, implode(",", $fieldNames), implode(', ', $values));

        return $sql;
    }

    /**
     * Get the sql query for all new translations added
     * @return string sql query
     */
    public function getNewTranslationSql()
    {
        $translations = $this->translationRepository->findBy(array('flag' => \Kunstmaan\TranslatorBundle\Entity\Translation::FLAG_NEW));

        return $this->buildInsertSql($translations, '\Kunstmaan\TranslatorBundle\Entity\Translation', array('flag'));
    }

    public function setTranslationRepository($translationRepository)
    {
        $this->translationRepository = $translationRepository;
    }

    public function setEntityManager($entityManager)
    {
        $this->entityManager = $entityManager;
    }
}
