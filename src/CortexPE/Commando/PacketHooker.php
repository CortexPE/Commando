<?php
declare(strict_types=1);

namespace libs\CortexPE\Commando;

use CortexPE\Commando\exception\HookAlreadyRegistered;
use CortexPE\Commando\store\SoftEnumStore;
use CortexPE\Commando\traits\IArgumentable;
use muqsit\simplepackethandler\SimplePacketHandler;
use pocketmine\command\CommandSender;
use pocketmine\event\EventPriority;
use pocketmine\event\Listener;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\serializer\AvailableCommandsPacketAssembler;
use pocketmine\network\mcpe\protocol\serializer\AvailableCommandsPacketDisassembler;
use pocketmine\network\mcpe\protocol\types\command\CommandData;
use pocketmine\network\mcpe\protocol\types\command\CommandHardEnum;
use pocketmine\network\mcpe\protocol\types\command\CommandOverload;
use pocketmine\network\mcpe\protocol\types\command\CommandParameter;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use function array_map;
use function array_product;
use function count;
use function spl_object_id;

class PacketHooker implements Listener{
    private static bool $isRegistered = false;
    private static bool $isIntercepting = false;

    public static function isRegistered() : bool{
        return self::$isRegistered;
    }

    /**
     *
     * As of 10/25/25 this has been updated to support the API changes dylan decided to push :/ (Commit d464862)
     * This has been updated by trix (dm me on discord if there is any issues @trix.pro)
     *
     * @throws HookAlreadyRegistered
     */
    public static function register(Plugin $registrant) : void{
        if(self::$isRegistered) {
            throw new HookAlreadyRegistered("Event listener is already registered by another plugin.");
        }

        $interceptor = SimplePacketHandler::createInterceptor($registrant, EventPriority::HIGHEST);
        $interceptor->interceptOutgoing(function(AvailableCommandsPacket $pk, NetworkSession $target) : bool{
            if(self::$isIntercepting){
                return true;
            }

            $player = $target->getPlayer();
            $disassembled = AvailableCommandsPacketDisassembler::disassemble($pk);
            $rebuiltCommandData = [];

            foreach($disassembled->commandData as $cmdData){
                $name = $cmdData->getName();
                $cmd = Server::getInstance()->getCommandMap()->getCommand($name);

                if($cmd instanceof BaseCommand){
                    foreach($cmd->getConstraints() as $constraint){
                        if(!$constraint->isVisibleTo($player)){
                            continue 2;
                        }
                    }

                    $overloads = self::generateOverloads($player, $cmd);

                    $rebuiltCommandData[] = new CommandData(
                        name: $cmdData->getName(),
                        description: $cmdData->getDescription(),
                        flags: $cmdData->getFlags(),
                        permission: $cmdData->getPermission(),
                        aliases: $cmdData->getAliases(),
                        overloads: $overloads,
                        chainedSubCommandData: $cmdData->getChainedSubCommandData()
                    );
                }else{
                    $rebuiltCommandData[] = $cmdData;
                }
            }

            $rebuilt = AvailableCommandsPacketAssembler::assemble(
                commandData: $rebuiltCommandData,
                hardcodedEnums: array_values($disassembled->unusedHardEnums),
                hardcodedSoftEnums: array_values($disassembled->unusedSoftEnums)
            );

            $rebuilt->softEnums = SoftEnumStore::getEnums();

            self::$isIntercepting = true;
            $target->sendDataPacket($rebuilt);
            self::$isIntercepting = false;

            return false;
        });

        self::$isRegistered = true;
    }

    /**
     * @return CommandOverload[]
     */
    private static function generateOverloads(CommandSender $cs, BaseCommand $command) : array{
        $overloads = [];

        foreach($command->getSubCommands() as $label => $subCommand){
            if(!$subCommand->testPermissionSilent($cs) || $subCommand->getName() !== $label){
                continue;
            }
            foreach($subCommand->getConstraints() as $constraint){
                if(!$constraint->isVisibleTo($cs)){
                    continue 2;
                }
            }

            $scParam = CommandParameter::enum(
                name: $label,
                enum: new CommandHardEnum("enum#".spl_object_id($subCommand), [$label]),
                flags: 0
            );

            $child = self::generateOverloads($cs, $subCommand);
            if(!empty($child)){
                foreach($child as $ov){
                    $overloads[] = new CommandOverload(false, [$scParam, ...$ov->getParameters()]);
                }
            }else{
                $overloads[] = new CommandOverload(false, [$scParam]);
            }
        }

        foreach(self::generateOverloadList($command) as $ov){
            $overloads[] = $ov;
        }

        return $overloads;
    }

    /**
     * @return CommandOverload[]
     */
    private static function generateOverloadList(IArgumentable $argumentable) : array{
        $input = $argumentable->getArgumentList();
        if($input === []){
            return [];
        }

        $combinations = [];
        $outputLength = array_product(array_map('count', $input));

        $indexes = [];
        foreach($input as $k => $_){
            $indexes[$k] = 0;
        }

        do{
            $set = [];
            foreach($indexes as $k => $idx){
                $param = $input[$k][$idx]->getNetworkParameterData();

                if(isset($param->enum) && $param->enum instanceof CommandHardEnum){
                    $param = clone $param;
                    $param->enum = new CommandHardEnum("enum#".spl_object_id($param), $param->enum->getValues());
                }

                $set[$k] = $param;
            }

            $combinations[] = new CommandOverload(false, $set);

            foreach($indexes as $k => $v){
                $indexes[$k]++;
                if($indexes[$k] >= count($input[$k])){
                    $indexes[$k] = 0;
                    continue;
                }
                break;
            }
        }while(count($combinations) !== $outputLength);

        return $combinations;
    }
}
