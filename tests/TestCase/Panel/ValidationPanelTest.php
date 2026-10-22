<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Panel;

use Cake\Controller\Controller;
use Cake\Event\Event;
use Cake\Form\Form;
use Cake\Http\ServerRequest;
use Cake\ORM\Entity;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Panel\ValidationPanel;

/**
 * Covers the CakePHP validation-error collection (entities + forms).
 */
class ValidationPanelTest extends TestCase
{
    protected ValidationPanel $panel;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->panel = new ValidationPanel();
    }

    /**
     * Entity errors (incl. nested) flatten to one row per field.
     *
     * @return void
     */
    public function testShutdownCollectsEntityErrors(): void
    {
        $address = new Entity(['city' => ''], ['markNew' => true]);
        $address->setErrors(['city' => ['_empty' => 'This field cannot be left empty.']]);

        $user = new Entity(
            ['username' => '', 'address' => $address],
            ['markNew' => true],
        );
        $user->setErrors(['username' => ['_empty' => 'This field cannot be left empty.']]);

        $controller = new Controller(new ServerRequest(['url' => '/admin/users/edit/1']));
        $controller->set('user', $user);

        $this->panel->shutdown(new Event('Controller.shutdown', $controller));

        $data = $this->panel->data();
        $this->assertArrayHasKey('errors', $data);
        $rows = [];
        foreach ($data['errors'] as $row) {
            $rows[$row['field']] = $row;
        }

        $this->assertArrayHasKey('user.username', $rows);
        $this->assertSame('This field cannot be left empty.', $rows['user.username']['message']);
        $this->assertSame(['_empty'], $rows['user.username']['failed_rules']);
        $this->assertSame('user', $rows['user.username']['bag']);
        $this->assertArrayHasKey('user.address.city', $rows);
        $this->assertSame('This field cannot be left empty.', $rows['user.address.city']['message']);
        $this->assertSame('2', $this->panel->summary());
    }

    /**
     * Plain form errors are collected under the view var name.
     *
     * @return void
     */
    public function testShutdownCollectsFormErrors(): void
    {
        $form = new class extends Form {
            /**
             * @param array $data Form data.
             * @return bool
             */
            protected function process(array $data): bool
            {
                return false;
            }
        };
        $form->setErrors(['email' => ['email' => 'Must be valid']]);

        $controller = new Controller(new ServerRequest(['url' => '/contact']));
        $controller->set('contact', $form);

        $this->panel->shutdown(new Event('Controller.shutdown', $controller));

        $data = $this->panel->data();
        $this->assertCount(1, $data['errors']);
        $this->assertSame('contact.email', $data['errors'][0]['field']);
        $this->assertSame('Must be valid', $data['errors'][0]['message']);
        $this->assertSame(['email'], $data['errors'][0]['failed_rules']);
    }

    /**
     * Association cycles terminate instead of hanging the request.
     *
     * @return void
     */
    public function testShutdownTerminatesOnCyclicGraphs(): void
    {
        $user = new Entity(['username' => ''], ['markNew' => true]);
        $user->setErrors(['username' => ['_empty' => 'This field cannot be left empty.']]);

        $role = new Entity(['name' => 'admin'], ['markNew' => true]);
        $user->set('roles', [$role]);
        $role->set('users', [$user]);

        $controller = new Controller(new ServerRequest(['url' => '/admin/users/edit/1']));
        $controller->set('user', $user);

        $this->panel->shutdown(new Event('Controller.shutdown', $controller));

        $data = $this->panel->data();
        $this->assertCount(1, $data['errors']);
        $this->assertSame('user.username', $data['errors'][0]['field']);
    }

    /**
     * Clean vars leave the empty state and a zero summary.
     *
     * @return void
     */
    public function testShutdownWithoutErrorsStaysEmpty(): void
    {
        $controller = new Controller(new ServerRequest(['url' => '/']));
        $controller->set('user', new Entity(['username' => 'bob'], ['markNew' => true]));

        $this->panel->shutdown(new Event('Controller.shutdown', $controller));

        $this->assertSame(['errors' => []], $this->panel->data());
        $this->assertSame('0', $this->panel->summary());
    }
}
