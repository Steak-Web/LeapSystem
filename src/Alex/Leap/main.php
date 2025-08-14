<?php
namespace Alex\Leap;

use pocketmine\player\Player;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\utils\TextFormat;
use pocketmine\math\Vector3;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;

class LeapSystem implements Listener {
    private array $leapStates = []; // Track leap state for each player
    private const LEAP_POWER = 0.8;
    private const LEAP_UPWARD_POWER = 0.6;

    public function __construct() {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->leapSystem = new LeapSystem($this);
    }

    public function onDrop(PlayerDropItemEvent $event): void {
        if ($this->isLeapFeather($event->getItem())) {
            $event->cancel();
        }
    }

    public function onInventoryTransaction(InventoryTransactionEvent $event): void {
        $transaction = $event->getTransaction();
        $player = $transaction->getSource();
        
        // Check all actions in the transaction
        foreach ($transaction->getInventories() as $inventory) {
            if ($inventory instanceof \pocketmine\inventory\PlayerInventory) {
                foreach ($transaction->getActions() as $action) {
                    if ($action instanceof \pocketmine\inventory\transaction\action\SlotChangeAction) {
                        $item = $action->getTargetItem();
                        
                        // Prevent moving leap feathers to other slots
                        if ($this->isLeapFeather($item)) {
                            $event->cancel();
                            return;
                        }
                    }
                }
            }
        }
    }

    public function onItemHeld(PlayerItemHeldEvent $event): void {
        $player = $event->getPlayer();
        $name = $player->getName();
        
        $newItem = $event->getItem();
        $oldItem = $player->getInventory()->getItemInHand();
        
        // If player was holding leap feather and now holding something else
        if ($this->isLeapFeather($oldItem) && !$this->isLeapFeather($newItem)) {
            // Reset leap state when switching away from leap feather
            $this->resetLeapState($name);
        }
        
        // If player is now holding leap feather
        if ($this->isLeapFeather($newItem)) {
            // Initialize leap state if not exists
            if (!isset($this->leapStates[$name])) {
                $this->leapStates[$name] = [
                    'firstLeapUsed' => false,
                    'secondLeapUsed' => false,
                    'slot3Used' => false,
                    'slot5Used' => false,
                    'canLeap' => true
                ];
            }
        }
    }

    public function onMove(PlayerMoveEvent $event): void {
        $player = $event->getPlayer();
        $name = $player->getName();
        
        // Only process if player is holding leap feather
        if (!$this->isHoldingLeapFeather($player)) {
            return;
        }
        
        // Initialize leap state if not exists
        if (!isset($this->leapStates[$name])) {
            $this->leapStates[$name] = [
                'firstLeapUsed' => false,
                'secondLeapUsed' => false,
                'slot3Used' => false,
                'slot5Used' => false,
                'canLeap' => true
            ];
        }
        
        $state = &$this->leapStates[$name];
        
        // Check which slot the player is currently holding
        $currentSlot = $player->getInventory()->getHeldItemIndex();
        
        // Only allow slots 3 and 5
        if ($currentSlot !== 3 && $currentSlot !== 5) {
            return;
        }
        
        // Check if this specific slot has already been used
        $slotKey = 'slot' . $currentSlot . 'Used';
        if ($state[$slotKey]) {
            return; // This slot has already been used
        }
        
        if ($player->isOnGround()) {
            // Player is on ground - can do first leap with any unused leap feather
            if (!$state['firstLeapUsed'] && $state['canLeap']) {
                $this->performLeap($player, 'first', $currentSlot);
                $state['firstLeapUsed'] = true;
                $state[$slotKey] = true; // Mark this slot as used
            }
        } else {
            // Player is in air - can do second leap with any unused leap feather if first leap was used
            if ($state['firstLeapUsed'] && !$state['secondLeapUsed'] && $state['canLeap']) {
                $this->performLeap($player, 'second', $currentSlot);
                $state['secondLeapUsed'] = true;
                $state[$slotKey] = true; // Mark this slot as used
                $state['canLeap'] = false; // No more leaps after using both
            }
        }
    }

    private function performLeap(Player $player, string $type, int $slot): void {
        $direction = $player->getDirectionVector();
        
        if ($type === 'first') {
            // First leap: forward and upward
            $player->setMotion(new Vector3(
                $direction->getX() * self::LEAP_POWER,
                self::LEAP_UPWARD_POWER,
                $direction->getZ() * self::LEAP_POWER
            ));
        } else {
            // Second leap: more forward momentum, less upward
            $player->setMotion(new Vector3(
                $direction->getX() * self::LEAP_POWER * 1.2,
                self::LEAP_UPWARD_POWER * 0.7,
                $direction->getZ() * self::LEAP_POWER * 1.2
            ));
        }
    }

    private function resetLeapState(string $playerName): void {
        if (isset($this->leapStates[$playerName])) {
            $this->leapStates[$playerName] = [
                'firstLeapUsed' => false,
                'secondLeapUsed' => false,
                'slot3Used' => false,
                'slot5Used' => false,
                'canLeap' => true
            ];
        }
    }

    private function isHoldingLeapFeather(Player $player): bool {
        return $this->isLeapFeather($player->getInventory()->getItemInHand());
    }

    private function isLeapFeather(Item $item): bool {
        return $item->getTypeId() === VanillaItems::FEATHER()->getTypeId() && 
               $item->getCustomName() === TextFormat::AQUA . "Super Fly";
    }

    public function giveLeapFeathers(Player $player): void {
        $feather = VanillaItems::FEATHER();
        $feather->setCustomName(TextFormat::AQUA . "Super Fly");
        $feather->setLore([
            TextFormat::YELLOW . "跳跳跳"
        ]);
        
        $player->getInventory()->setItem(3, $feather);
        $player->getInventory()->setItem(5, $feather->setCount(1));
    }
}
