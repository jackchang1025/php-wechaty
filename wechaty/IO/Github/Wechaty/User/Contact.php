<?php
/**
 * Created by PhpStorm.
 * User: peterzhang
 * Date: 2020/7/20
 * Time: 10:23 AM
 */
namespace IO\Github\Wechaty\User;

use IO\Github\Wechaty\Accessory;
use IO\Github\Wechaty\Exceptions\WechatyException;
use IO\Github\Wechaty\Puppet\FileBox\FileBox;
use IO\Github\Wechaty\Puppet\Schemas\ContactPayload;
use IO\Github\Wechaty\PuppetService\PuppetService;
use IO\Github\Wechaty\Type\Sayable;
use IO\Github\Wechaty\Util\Logger;

class Contact extends Accessory implements Sayable {
    /**
     * @var null|ContactPayload
     */
    protected ?ContactPayload $_payload = null;
    /**
     * @var null|PuppetService
     */
    protected $_puppet = null;

    public function __construct($wechaty, $id = "") {
        $this->_id = $id;
        $this->_puppet = $wechaty->getPuppet();
        parent::__construct($wechaty);
    }

    function say($something) : ?Message {
        $msgId = "";

        $conversationId = $this->getId();
        if(gettype($something) == "string") {
            $msgId = $this->_puppet->messageSendText($conversationId, $something);
        } else if($something instanceof Contact) {
            $msgId = $this->_puppet->messageSendContact($conversationId, $something);
        } else if($something instanceof FileBox) {
            $msgId = $this->_puppet->messageSendFile($conversationId, $something);
        } else if($something instanceof UrlLink) {
            $msgId = $this->_puppet->messageSendUrl($conversationId, $something->getPayload());
        } else if($something instanceof MiniProgram) {
            $msgId = $this->_puppet->messageSendMiniProgram($conversationId, $something->getPayload());
        } else {
            throw new WechatyException("unknow message");
        }

        if ($msgId != null) {
            $message = $this->wechaty->messageManager->load($msgId);
            $message->ready();
            return $message;
        }

        return null;
    }

    function contactList() : array {
        $contactList = $this->_puppet->contactList();
        $contactObjs = array();
        foreach($contactList as $value) {
            if($value == $this->_id) {
                break;
            }
            $contact = $this->wechaty->contactManager->load($value);
            $contact->ready();
            $contactObjs[] = $contact;
        }

        return $contactObjs;
    }

    function saySomething($something, Contact $contact) {
        $conversationId = $this->getId();
        if(gettype($something) == "string") {
            $msgId = $this->_puppet->messageSendText($conversationId, $something);
        }
        return null;
    }

    function sync() {
        return $this->ready(true);
    }

    function getPayload() : ContactPayload {
        return $this->_payload;
    }

    function ready(bool $forceSyn = false) {
        if (!$forceSyn && $this->isReady()) {
            return true;
        }
        try {
            if ($forceSyn) {
                $this->_puppet->contactPayloadDirty($this->_id);
            }
            $this->_payload = $this->_puppet->contactPayload($this->_id);
        } catch (\Exception $e) {
            Logger::ERR("ready() contactPayload {} error ", $this->_id, $e);
            throw $e;
        }
    }

    function isReady() : bool {
        return ($this->_payload != null && !empty($this->_payload->name));
    }

    function avatar() {
        try {
            return $this->wechaty->getPuppet()->getContactAvatar($this->_id);
        } catch (\Exception $e) {
            Logger::ERR("error", $e);
        }
        return false;
    }

    function name() : String {
        return $this->_payload->name ?: "";
    }

    function setAlias(String $newAlias) {
        if($this->_payload == null) {
            throw new WechatyException("no payload");
        }
        try {
            $this->_puppet->contactAlias($this->_id, $newAlias);
            $this->_puppet->contactPayloadDirty($this->_id);
            $this->_payload = $this->_puppet->contactPayload($this->_id);
        } catch (\Exception $e) {
            Logger::ERR("alias({}) rejected: {}", $newAlias, $e->getMessage());
            throw $e;
        }
    }

    function getAlias() : ?String {
        return $this->_payload->alias ?:null;
    }

    function stranger(): bool {
        if(empty($this->friend())) {
            return false;
        } else {
            return !$this->friend();
        }
    }

    function friend(): bool {
        return $this->_payload->friend;
    }

    function type(): int {
        if(empty($this->_payload)) {
            throw new WechatyException("no payload");
        }

        return $this->_payload->type;
    }

    function gender(): int {
        return $this->_payload->gender ?: ContactPayload::CONTACTGENDER_UNKNOWN;
    }

    function province() : String {
        return $this->_payload->province;
    }

    function city() : String {
        return $this->_payload->city;
    }

    function tags(): array {
        $tagIdList = $this->wechaty->getPuppet()->tagContactList($this->_id);
        try {
            $that = $this;
            $tagList = array_map(function($value) use ($that) {
                $that->wechaty->tagManager->load($value);
            }, $tagIdList);
            return $tagList;
        } catch (\Exception $e) {
            Logger::ERR("error", $e);
            return array();
        }
    }

    function self(): bool {
        $userId = $this->_puppet->selfId();
        if(empty($userId)) {
            return false;
        }
        return $this->_id == $userId;
    }
}