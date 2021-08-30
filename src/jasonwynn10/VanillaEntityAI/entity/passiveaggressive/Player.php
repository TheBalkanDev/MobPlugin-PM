<?php
declare(strict_types=1);
namespace jasonwynn10\VanillaEntityAI\entity\passiveaggressive;

use jasonwynn10\VanillaEntityAI\block\Pumpkin;
use jasonwynn10\VanillaEntityAI\entity\hostile\Enderman;
use jasonwynn10\VanillaEntityAI\entity\Interactable;
use jasonwynn10\VanillaEntityAI\entity\Linkable;
use jasonwynn10\VanillaEntityAI\entity\Lookable;
use jasonwynn10\VanillaEntityAI\network\PlayerNetworkSessionAdapter;
use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\ActorPickRequestPacket;
use pocketmine\network\mcpe\protocol\InteractPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\PlayerInputPacket;
use pocketmine\network\SourceInterface;

class Player extends \pocketmine\Player {
	/**
	 * @param SourceInterface $interface
	 * @param string $ip
	 * @param int $port
	 */
	public function __construct(SourceInterface $interface, string $ip, int $port) {
		parent::__construct($interface, $ip, $port);
		$this->sessionAdapter = new PlayerNetworkSessionAdapter($this->server, $this);
	}

	/**
	 * @param ActorPickRequestPacket $packet
	 *
	 * @return bool
	 */
	public function handleEntityPickRequest(ActorPickRequestPacket $packet) : bool {
		$target = $this->level->getEntity($packet->entityUniqueId);
		if($target === null) {
			return false;
		}
		if($this->isCreative()) {
			$item = Item::get(Item::MONSTER_EGG, $target::NETWORK_ID, 64);
			if(!empty($target->getNameTag())) {
				$item->setCustomName($target->getNameTag());
			}
			$this->getInventory()->setItem($packet->hotbarSlot, $item);
		}
		return true;
	}

	/**
	 * @param PlayerInputPacket $packet
	 *
	 * @return bool
	 */
	public function handlePlayerInput(PlayerInputPacket $packet) : bool {
		return false; // TODO
	}

	/**
	 * @param InteractPacket $packet
	 *
	 * @return bool
	 */
	public function handleInteract(InteractPacket $packet) : bool {
		$return = parent::handleInteract($packet);
		switch($packet->action) {
			case InteractPacket::ACTION_LEAVE_VEHICLE:
				$target = $this->level->getEntity($packet->target);
				$this->setTargetEntity($target);
				if($target instanceof Linkable) {
					$target->unlink();
				}
			break;
			case InteractPacket::ACTION_MOUSEOVER:
				$target = $this->level->getEntity($packet->target);
				$this->setTargetEntity($target);
				// TODO: check distance
				if($target instanceof Lookable) {
					if($target instanceof Enderman and $this->getArmorInventory()->getHelmet() instanceof Pumpkin)
						break;
					$target->onPlayerLook($this);
				}elseif($target === null) {
					$this->getDataPropertyManager()->setString(Entity::DATA_INTERACTIVE_TAG, ""); // Don't show button anymore
				}
				$return = true;
			break;
			default:
				$this->server->getLogger()->debug("Unhandled/unknown interaction type " . $packet->action . "received from " . $this->getName());
				$return = false;
		}
		return $return;
	}

	/**
	 * @param InventoryTransactionPacket $packet
	 *
	 * @return bool
	 */
	public function handleInventoryTransaction(InventoryTransactionPacket $packet) : bool {
		$return = parent::handleInventoryTransaction($packet);
		if($packet->trData->getTypeId() === InventoryTransactionPacket::TYPE_USE_ITEM_ON_ENTITY);
			$type = $packet->trData->actionType;
			switch($type) {
				case InventoryTransactionPacket::USE_ITEM_ON_ENTITY_ACTION_INTERACT:
					$target = $this->level->getEntity($packet->trData->entityRuntimeId);
					$this->setTargetEntity($target);
					$this->getDataPropertyManager()->setString(Entity::DATA_INTERACTIVE_TAG, ""); // Don't show button anymore
					if($target instanceof Interactable) {
						$target->onPlayerInteract($this);
						return true;
						break;
					}
			}
		return true;
	}
}
