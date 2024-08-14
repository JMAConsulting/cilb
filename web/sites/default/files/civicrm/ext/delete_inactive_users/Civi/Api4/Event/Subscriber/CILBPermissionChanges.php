<?php

namespace Civi\Api4\Event\Subscriber;

use Civi\API\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber to check extra permission for the List Manager application
 * @service civi.api4.listmanager
 */
class CILBPermissionChanges extends \Civi\Core\Service\AutoService implements EventSubscriberInterface {

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array {
        return [
            'civi.api.authorize' => [
                ['onApiAuthorize', Events::W_LATE],
            ],
        ];
    }

    /**
     * Alters APIv4 permissions to allow users with 'access list manager' to call a range
     * of API entities and actions based on the requirements of the List Manager application
     *
     * @param \Civi\API\Event\AuthorizeEvent $event
     *   API authorization event.
     */
    public function onApiAuthorize(\Civi\API\Event\AuthorizeEvent $event): void {
        $apiRequest = $event->getApiRequest();
        if ($apiRequest['version'] == 4) {
            $entity = $apiRequest->getEntityName();
            $action = strtolower($apiRequest->getActionName());
            if ($action == 'get' && ($entity == 'Event' || $entity == 'PriceSetEntity' || $entity == 'PriceFieldValue')) {
                if (\CRM_Core_Permission::check('access ajax api')) {
                    $event->authorize();
                    $event->stopPropagation();
                }
            } 
        }
    }
}