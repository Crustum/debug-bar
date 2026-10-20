<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Mailer;

use Cake\Mailer\MailerAwareTrait;
use Cake\ORM\Locator\LocatorAwareTrait;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

/**
 * Base class for Mailer Previews.
 *
 * @ported-from \DebugKit\Mailer\MailPreview
 */
class MailPreview
{
    use MailerAwareTrait;
    use LocatorAwareTrait;

    /**
     * Returns the name of an email if it is valid
     *
     * @param string $email Email name
     * @return string|null
     */
    public function find(string $email): ?string
    {
        if ($this->validEmail($email)) {
            return $email;
        }

        return null;
    }

    /**
     * Returns a list of valid emails
     *
     * @return array
     */
    public function getEmails(): array
    {
        $emails = [];
        foreach (get_class_methods($this) as $methodName) {
            if (!$this->validEmail($methodName)) {
                continue;
            }

            $emails[] = $methodName;
        }

        return $emails;
    }

    /**
     * Returns the name of this preview
     *
     * @return string
     */
    public function name(): string
    {
        $classname = static::class;
        $pos = strrpos($classname, '\\');

        return substr($classname, $pos + 1);
    }

    /**
     * Returns whether or not a specified email is valid
     * for this MailPreview instance
     *
     * @param string $email Name of email
     * @return bool
     */
    protected function validEmail(string $email): bool
    {
        if (empty($email)) {
            return false;
        }

        $baseClass = new ReflectionClass(self::class);
        if ($baseClass->hasMethod($email)) {
            return false;
        }

        try {
            $method = new ReflectionMethod($this, $email);
        } catch (ReflectionException) {
            return false;
        }

        return $method->isPublic();
    }
}
