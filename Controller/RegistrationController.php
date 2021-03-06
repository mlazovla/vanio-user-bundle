<?php
namespace Vanio\UserBundle\Controller;

use FOS\UserBundle\Controller\RegistrationController as BaseRegistrationController;
use FOS\UserBundle\Event\FormEvent;
use FOS\UserBundle\Model\UserManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Vanio\UserBundle\VanioUserEvents;
use Vanio\WebBundle\Request\RefererHelperTrait;
use Vanio\WebBundle\Translation\FlashMessage;

class RegistrationController extends BaseRegistrationController
{
    use RefererHelperTrait;

    /**
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
     * @param Request $request
     * @param string $token
     * @return Response
     */
    public function confirmAction(Request $request, $token): Response
    {
        try {
            return parent::confirmAction($request, $token);
        } catch (NotFoundHttpException $e) {
            $this->addFlashMessage(FlashMessage::TYPE_DANGER, 'registration.flash.confirmation_token_not_found');

            return $this->redirectToReferer();
        }
    }

    public function sendConfirmationAction(Request $request, string $email): Response
    {
        if ($user = $this->userManager()->findUserByEmail($email)) {
            if ($user->isEnabled()) {
                $this->addFlashMessage(FlashMessage::TYPE_INFO, 'registration.flash.already_confirmed');

                return $this->redirectToRoute('fos_user_security_login');
            }

            $event = new FormEvent($this->createForm(FormType::class, $user), $request);
            $this->eventDispatcher()->dispatch(VanioUserEvents::REGISTRATION_CONFIRMATION_REQUESTED, $event);

            if (!$response = $event->getResponse()) {
                throw new \LogicException('You need to enable email confirmation inside your "fos_user" configuration.');
            }

            $this->userManager()->updateUser($user);

            return $response;
        }

        $this->addFlashMessage(FlashMessage::TYPE_DANGER, 'registration.flash.user_not_found');

        return $this->redirectToReferer();
    }

    private function addFlashMessage(string $type, string $message, array $parameters = [])
    {
        $this->addFlash($type, new FlashMessage($message, $parameters, 'FOSUserBundle'));
    }

    private function eventDispatcher(): EventDispatcherInterface
    {
        return $this->get('event_dispatcher');
    }

    private function userManager(): UserManagerInterface
    {
        return $this->get('fos_user.user_manager');
    }
}
