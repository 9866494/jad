<?php declare(strict_types=1);

namespace Jad\Tests\CRUD;

use Jad\Common\ClassHelper;
use Jad\Map\AnnotationsMapper;
use Jad\Tests\TestCase;
use Jad\CRUD\Create;
use Jad\Database\Manager;
use Jad\Database\Entities\Invoices;

class CreateTest extends TestCase
{
    /**
     * @throws \Doctrine\Common\Annotations\AnnotationException
     * @throws \Jad\Exceptions\JadException
     * @throws \ReflectionException
     */
    public function testAddRelationshipTest()
    {
        $request = $this->getMockBuilder('Jad\Request\JsonApiRequest')
            ->setMethods(['getInputJson', 'getMethod'])
            ->disableOriginalConstructor()
            ->getMock();

        $mapper = new AnnotationsMapper(Manager::getInstance()->getEm());

        $create = new Create($request, $mapper);

        $method = $this->getMethod('Jad\CRUD\Create', 'addRelationships');

        $entity = new Invoices();
        ClassHelper::setPropertyValue($entity, 'invoiceDate', new \DateTime('2018-01-01'));
        ClassHelper::setPropertyValue($entity, 'billingAddress', 'River street 14');
        ClassHelper::setPropertyValue($entity, 'billingCity', 'Westham');
        ClassHelper::setPropertyValue($entity, 'billingPostalCode', 'WE345R');
        ClassHelper::setPropertyValue($entity, 'total', '2.64');

        $method->invokeArgs($create, [$this->getInput(), $entity]);

        /** @var \Jad\Database\Entities\Customers $customers */
        $customers = ClassHelper::getPropertyValue($entity, 'customers');
        $this->assertEquals('53' ,ClassHelper::getPropertyValue($customers, 'id'));

        /** @var \Doctrine\Common\Collections\ArrayCollection $collection */
        $collection = ClassHelper::getPropertyValue($entity, 'invoiceItems');
        $this->assertEquals('10' ,ClassHelper::getPropertyValue($collection->first(), 'id'));
    }

    private function getInput()
    {
        $input = new \stdClass();
        $input->data = new \stdClass();
        $input->data->type = 'invoices';
        $input->data->attributes = new \stdClass();
        $input->data->attributes->{'invoice-date'} = '2018-01-01 00:00:00';
        $input->data->attributes->{'billing-address'} = 'River street 14';
        $input->data->attributes->{'billing-city'} = 'Westham';
        $input->data->attributes->{'billing-state'} = null;
        $input->data->attributes->{'billing-postal-code'} = 'WE345R';
        $input->data->attributes->{'total'} = '2.64';

        $input->data->relationships = new \stdClass();
        $input->data->relationships->customers = new \stdClass();
        $input->data->relationships->{'invoice-items'} = new \stdClass();

        $customers = new \stdClass();
        $customers->data = new \stdClass();
        $customers->data->id = '53';
        $customers->data->type = 'customers';

        $input->data->relationships->customers = $customers;

        $item = new \stdClass();
        $item->data = new \stdClass();
        $item->data->id = '10';
        $item->data->type = 'invoice-items';

        $input->data->relationships->{'invoice-items'} = $item;

        return $input;


    }
}