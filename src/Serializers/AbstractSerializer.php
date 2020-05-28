<?php declare(strict_types=1);

namespace Jad\Serializers;

use Jad\Map\CacheStorage;
use Jad\Map\Mapper;
use Jad\Request\JsonApiRequest;
use Jad\Common\Text;
use Jad\Common\ClassHelper;
use Jad\Map\MapItem;
use Jad\Exceptions\SerializerException;
use Doctrine\Common\Annotations\AnnotationReader;
use Jad\Serializers\AbstractSerializer\MergedField;
use Doctrine\Common\Util\ClassUtils;

/**
 * Class AbstractSerializer
 * @package Jad\Serializers
 */
abstract class AbstractSerializer implements Serializer
{
    const DATE_FORMAT = 'Y-m-d';
    const TIME_FORMAT = 'H:i:s';
    const DATE_TIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * @var Mapper $mapper
     */
    protected $mapper;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var JsonApiRequest $request
     */
    protected $request;

    /**
     * EntitySerializer constructor.
     * @param Mapper $mapper
     * @param $type
     * @param JsonApiRequest $request
     */
    public function __construct(Mapper $mapper, string $type, JsonApiRequest $request)
    {
        $this->mapper = $mapper;
        $this->type = $type;
        $this->request = $request;
    }

    /**
     * @param $entity
     * @return string
     * @throws \Exception
     * @throws \Jad\Exceptions\JadException
     */
    public function getId($entity): string
    {
        return (string)ClassHelper::getPropertyValue($entity, $this->getMapItem()->getIdField());
    }

    /**
     * @return MapItem
     * @throws \Exception
     */
    public function getMapItem(): MapItem
    {
        $mapItem = $this->mapper->getMapItem($this->type);
        if (!$mapItem instanceof MapItem) {
            throw new SerializerException('Could not find map item for type: ' . $this->type);
        }
        return $mapItem;
    }

    /**
     * @param $entity
     * @return mixed|string
     * @throws \Exception
     */
    public function getType($entity): string
    {
        return $this->getMapItem()->getType();
    }

    /**
     * @param $entity
     * @param array|null $fields
     * @return array|mixed
     * @throws \Exception
     * @throws \Jad\Exceptions\JadException
     */
    public function getAttributes($entity, ?array $fields): array
    {
        $entityClass = ClassUtils::getClass($entity);

        $cache = $this->mapper->getCache();
        $mapperCacheKey = $this->mapper->getCacheKey();
        $cacheKey = $mapperCacheKey . '\\' . static::class . '=>' . $entityClass . '::' . md5(json_encode($fields));
        $attributes = [];

        if ($cache instanceof CacheStorage && $cache->hasItem($cacheKey)) {
            $mergedFields = $cache->getItem($cacheKey);
        } else {
            $reader = new AnnotationReader();

            if (is_array($fields)) {
                $fields = array_map(function ($field) {
                    return Text::deKebabify($field);
                }, $fields);
            }

            $metaFields = $this->getMapItem()->getClassMeta()->getFieldNames();
            $reflection = new \ReflectionClass($entityClass);
            $classFields = array_keys($reflection->getDefaultProperties());

            $mergedFieldsList = array_unique(array_merge($metaFields, $classFields));

            $mergedFields = [];

            foreach ($mergedFieldsList as $field) {
                // Do not display association
                if ($this->getMapItem()->getClassMeta()->hasAssociation($field)) {
                    continue;
                }

                // If filtered fields, only show selected fields
                if (!empty($fields) && !in_array($field, $fields)) {
                    continue;
                }

                try {

                    $mergedField = new MergedField();

                    $jadAnnotation = $reader->getPropertyAnnotation(
                        $reflection->getProperty($field),
                        'Jad\Map\Annotations\Attribute'
                    );

                    $annotation = $reader->getPropertyAnnotation(
                        $reflection->getProperty($field),
                        'Doctrine\ORM\Mapping\Column'
                    );

                    $mergedField->setJadAnnotation($jadAnnotation);
                    $mergedField->setAnnotation($annotation);

                    $mergedFields[$field] = $mergedField;
                } catch (\Throwable $throwable) {

                }
            }

            if ($cache instanceof CacheStorage) {
                $cache->setItem($cacheKey, $mergedFields);
            }
        }

        /** @var MergedField $mergedField */
        foreach ($mergedFields as $field => $mergedField) {

            $jadAnnotation = $mergedField->getJadAnnotation();

            if (!is_null($jadAnnotation)) {
                if (property_exists($jadAnnotation, 'visible')) {
                    $visible = is_null($jadAnnotation->visible) ? true : (bool)$jadAnnotation->visible;

                    if (!$visible) {
                        continue;
                    }
                }
            }

            $fieldValue = ClassHelper::getPropertyValue($entity, $field);
            $value = $fieldValue;

            $annotation = $mergedField->getAnnotation();

            if ($fieldValue instanceof \DateTime) {
                $value = $this->getDateTime($fieldValue, $annotation->type ?? null);
            }

            $attributes[Text::kebabify($field)] = $value;
        }

        return $attributes;
    }

    /**
     * @param \DateTime $value
     * @param string $dateType
     * @return string
     */
    protected function getDateTime(\DateTime $value, $dateType = 'datetime'): string
    {
        switch ($dateType) {
            case 'date':
                return $value->format(self::DATE_FORMAT);

            case 'time':
                return $value->format(self::TIME_FORMAT);

            default:
                return $value->format(self::DATE_TIME_FORMAT);
        }
    }

    /**
     * @param string $type
     * @param $collection
     * @param array $fields
     * @return array
     */
    public function getIncludedResources(string $type, $collection, array $fields = []): array
    {
        return [];
    }
}