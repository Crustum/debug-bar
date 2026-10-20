<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Controller;

use Cake\Collection\CollectionInterface;
use Cake\Core\App;
use Cake\Core\Plugin as CorePlugin;
use Cake\Event\EventInterface;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\ServerRequest;
use Cake\Routing\Router;
use Cake\Utility\Inflector;
use Crustum\DebugBar\Mailer\AbstractResult;
use Crustum\DebugBar\Mailer\MailPreview;
use Crustum\DebugBar\Mailer\PreviewResult;
use Crustum\DebugBar\Mailer\SentMailResult;
use Psr\Http\Message\ResponseInterface;
use function Cake\Collection\collection;

/**
 * Provides access to the MailPreview classes for visually debugging email sending
 *
 * @property \Crustum\DebugBar\Model\Table\PanelsTable $Panels
 * @ported-from \DebugKit\Controller\MailPreviewController
 */
class MailPreviewController extends DebugBarController
{
    /**
     * Before render handler.
     *
     * @param \Cake\Event\EventInterface $event The event.
     * @return void
     */
    public function beforeRender(EventInterface $event): void
    {
        $this->viewBuilder()->setLayout('Crustum/DebugBar.mailer');
    }

    /**
     * Handles mail-preview/index
     *
     * @return void
     */
    public function index(): void
    {
        $this->set('mailPreviews', $this->getMailPreviews()->toArray());
    }

    /**
     * Handles the viewing of an already sent email that was logged in the Mail panel
     * for DebugBar
     *
     * @param string $panelId The Mail panel id where the email data is stored.
     * @param string $number The email number as stored in the logs.
     * @return \Psr\Http\Message\ResponseInterface|null
     */
    public function sent(string $panelId, string $number): ?ResponseInterface
    {
        /** @var \Crustum\DebugBar\Model\Entity\Panel $panel */
        $panel = $this->fetchTable('Crustum/DebugBar.Panels')->get($panelId);

        // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
        $content = @unserialize($panel->content);

        if (empty($content['emails'][$number])) {
            throw new NotFoundException('No emails found in this request');
        }

        $email = $content['emails'][$number];
        $email = new SentMailResult(array_filter($email['headers']), $email['message']);

        /** @var string $partType */
        $partType = $this->request->getQuery('part');
        if ($partType) {
            return $this->respondWithPart($email, $partType);
        }

        /** @var string $part */
        $part = $this->request->getQuery('part');
        $this->set('noHeader', true);
        $this->set('email', $email);
        $this->set('plugin', '');
        $this->set('part', $this->findPreferredPart($email, $part));
        $this->viewBuilder()->setTemplate('email');

        return null;
    }

    /**
     * Serves a retained attachment body for download.
     *
     * Only bodies kept within the transport capture budget are served;
     * metadata-only attachments 404 instead of fake bytes.
     *
     * @param string $panelId The Mail panel id where the email data is stored.
     * @param string $number The email number as stored in the logs.
     * @param string $index The attachment index within the email.
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Cake\Http\Exception\NotFoundException When the body was not retained.
     */
    public function attachment(string $panelId, string $number, string $index): ResponseInterface
    {
        /** @var \Crustum\DebugBar\Model\Entity\Panel $panel */
        $panel = $this->fetchTable('Crustum/DebugBar.Panels')->get($panelId);

        // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
        $content = @unserialize($panel->content);

        $attachment = $content['emails'][$number]['attachments'][$index] ?? null;
        if (!is_array($attachment)) {
            throw new NotFoundException('Attachment not found in this request');
        }

        $body = base64_decode((string)($attachment['body_base64'] ?? ''), true);
        if (!is_string($attachment['body_base64'] ?? null) || $body === false) {
            throw new NotFoundException('Attachment body was not retained');
        }

        $name = (string)($attachment['name'] ?? 'attachment');
        $response = $this->response
            ->withType((string)($attachment['content_type'] ?? 'application/octet-stream'))
            ->withDownload($name);

        return $response->withStringBody($body);
    }

    /**
     * Handles mail-preview/email
     *
     * @param string $name The Mailer name
     * @param string $method The mailer preview method
     * @return \Psr\Http\Message\ResponseInterface|null
     */
    public function email(string $name, string $method): ?ResponseInterface
    {
        $restore = Router::getRequest();
        Router::setRequest($this->request->withParam('plugin', null));

        /** @var string $plugin */
        $plugin = $this->request->getQuery('plugin');
        $email = $this->findPreview($name, $method, $plugin);
        $partType = $this->request->getQuery('part');

        $this->viewBuilder()->disableAutoLayout();

        if ($partType) {
            $result = $this->respondWithPart($email, $partType);
            if ($restore instanceof ServerRequest) {
                Router::setRequest($restore);
            }

            return $result;
        }

        $humanName = Inflector::humanize(Inflector::underscore($name) . "_$method");
        /** @var string $part */
        $part = $this->request->getQuery('part');
        $this->set('title', $humanName);
        $this->set('email', $email);
        $this->set('plugin', $plugin);
        $this->set('part', $this->findPreferredPart($email, $part));

        if ($restore instanceof ServerRequest) {
            Router::setRequest($restore);
        }

        return null;
    }

    /**
     * Returns a response object with the requested part type for the
     * email or throws an exception, if no such part exists.
     *
     * @param \Crustum\DebugBar\Mailer\AbstractResult $email the email to preview
     * @param string $partType The email part to retrieve
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function respondWithPart(AbstractResult $email, string $partType): ResponseInterface
    {
        $part = $this->findPart($email, $partType);

        if ($part === null) {
            throw new NotFoundException(sprintf("Email part '%s' not found in email", $partType));
        }

        $response = $this->response->withType($partType);
        if ($partType === 'text') {
            $part = '<pre>' . $part . '</pre>';
        }

        return $response->withStringBody($part);
    }

    /**
     * Retrieves an array of MailPreview objects
     *
     * @return \Cake\Collection\CollectionInterface
     */
    protected function getMailPreviews(): CollectionInterface
    {
        return $this->getMailPreviewClasses()->groupBy('plugin');
    }

    /**
     * Returns an array of MailPreview class names for the app and plugins
     *
     * @return \Cake\Collection\CollectionInterface
     */
    protected function getMailPreviewClasses(): CollectionInterface
    {
        $pluginPaths = collection(CorePlugin::loaded())
            ->reject(fn($plugin): bool => $plugin === 'Crustum/DebugBar')
            ->map(fn($plugin): array => [[CorePlugin::classPath($plugin) . 'Mailer/Preview/'], "$plugin."]);

        $appPaths = [App::classPath('Mailer/Preview'), ''];

        return collection([$appPaths])
            ->append($pluginPaths)
            ->unfold(function ($pairs) {
                [$paths, $plugin] = $pairs;
                foreach ($paths as $path) {
                    yield $plugin => $path;
                }
            })
            ->unfold(function ($path, $plugin) {
                /** @var list<string> $files */
                $files = glob($path . '*Preview.php');
                foreach ($files as $file) {
                    $base = str_replace('.php', '', basename($file));
                    $class = App::className($plugin . $base, 'Mailer/Preview');
                    if ($class) {
                        yield ['plugin' => trim($plugin, '.'), 'class' => new $class()];
                    }
                }
            });
    }

    /**
     * Finds a specified email part
     *
     * @param \Crustum\DebugBar\Mailer\AbstractResult $email The result of the email preview
     * @param string $partType The name of a part
     * @return string|null
     */
    protected function findPart(AbstractResult $email, string $partType): ?string
    {
        return $email->getParts()[$partType] ?? null;
    }

    /**
     * Finds a specified email part or the first part available
     *
     * @param \Crustum\DebugBar\Mailer\AbstractResult $email The result of the email preview
     * @param string|null $partType The name of a part
     * @return string|null
     */
    protected function findPreferredPart(AbstractResult $email, ?string $partType): ?string
    {
        $parts = $email->getParts();

        if ($partType === null && !empty($parts['html'])) {
            return 'html';
        }

        if ($partType === null) {
            foreach ($email->getParts() as $part => $content) {
                return $part;
            }
        }

        return $this->findPart($email, (string)$partType) ?: null;
    }

    /**
     * Returns a matching MailPreview object with name
     *
     * @param string $previewName The Mailer name
     * @param string $emailName The mailer preview method
     * @param ?string $plugin The plugin where the mailer preview should be found
     * @return \Crustum\DebugBar\Mailer\PreviewResult The result of the email preview
     * @throws \Cake\Http\Exception\NotFoundException
     */
    protected function findPreview(string $previewName, string $emailName, ?string $plugin = null): PreviewResult
    {
        if ($plugin) {
            $plugin = "$plugin.";
        }

        if (str_contains($previewName, '\\')) {
            throw new NotFoundException("Mailer preview $previewName not found");
        }

        $realClass = App::className($plugin . $previewName, 'Mailer/Preview');
        if (!$realClass || !is_subclass_of($realClass, MailPreview::class, true)) {
            throw new NotFoundException("Mailer preview $previewName not found");
        }

        /** @var \Crustum\DebugBar\Mailer\MailPreview $mailPreview */
        $mailPreview = new $realClass();

        $email = $mailPreview->find($emailName);
        if ($email === null) {
            throw new NotFoundException(sprintf(
                'Mailer preview `%s::%s` not found',
                $previewName,
                $emailName,
            ));
        }

        return new PreviewResult($mailPreview->$email(), $email);
    }
}
