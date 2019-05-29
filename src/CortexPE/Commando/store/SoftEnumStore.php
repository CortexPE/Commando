<?php


namespace CortexPE\Commando\store;


use CortexPE\Commando\exception\CommandoException;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\types\CommandEnum;
use pocketmine\network\mcpe\protocol\UpdateSoftEnumPacket;
use pocketmine\Server;

class SoftEnumStore {
	/** @var CommandEnum[] */
	private static $enums = [];

	public static function getEnumByName(string $name):?CommandEnum {
		return static::$enums[$name] ?? null;
	}

	/**
	 * @return CommandEnum[]
	 */
	public static function getEnums(): array {
		return static::$enums;
	}

	public static function addEnum(CommandEnum $enum):void {
		if($enum->enumName === null){
			throw new CommandoException("Invalid enum");
		}
		static::$enums[$enum->enumName] = $enum;
		self::broadcastSoftEnum($enum, UpdateSoftEnumPacket::TYPE_ADD);
	}

	public static function updateEnum(string $enumName, array $values):void {
		if(($enum = self::getEnumByName($enumName)) === null){
			throw new CommandoException("Unknown enum named " . $enumName);
		}
		$enum->enumValues = $values;
		self::broadcastSoftEnum($enum, UpdateSoftEnumPacket::TYPE_SET);
	}

	public static function removeEnum(string $enumName):void {
		if(($enum = self::getEnumByName($enumName)) === null){
			throw new CommandoException("Unknown enum named " . $enumName);
		}
		unset(static::$enums[$enumName]);
		self::broadcastSoftEnum($enum, UpdateSoftEnumPacket::TYPE_REMOVE);
	}

	public static function broadcastSoftEnum(CommandEnum $enum, int $type):void {
		$pk = new UpdateSoftEnumPacket();
		$pk->enumName = $enum->enumName;
		$pk->values = $enum->enumValues;
		$pk->type = $type;
		self::broadcastPacket($pk);
	}

	private static function broadcastPacket(DataPacket $pk):void {
		($sv = Server::getInstance())->broadcastPacket($sv->getOnlinePlayers(), $pk);
	}
}