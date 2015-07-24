<?php namespace LaravelDoctrine\ORM;

use Doctrine\Common\Annotations\AnnotationRegistry;

class DoctrineExtender {

    /**
     * @param array $typeMap
     * @throws \Doctrine\DBAL\DBALException
     */
    public function addCustomTypes(array $typeMap)
    {
        foreach ($typeMap as $name => $class) {
            if (!Type::hasType($name)) {
                Type::addType($name, $class);
            } else {
                Type::overrideType($name, $class);
            }
        }
    }

    public function registerAnnotations($customAnnotationsMap)
    {
        foreach($customAnnotationsMap as $name => $file)
        {
            AnnotationRegistry::registerFile($file);
        }
    }

}