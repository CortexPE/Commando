<?php


namespace CortexPE\Commando\args\selector;


use function array_keys;
use function implode;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use function preg_match;
use function preg_match_all;

class SelectorParser {
	/** @var BaseSelector[] */
	private $selectors = [];
	/** @var string  */
	private $selRegex = "";

	public function registerSelector(BaseSelector $selector):void {
		$c = strtolower($selector->getChar(){0});
		if(!isset($this->selectors[$c])){
			$this->selectors[$c] = $selector;
		}
		$this->selRegex = "/(?:@([" . implode("", array_keys($this->selectors)) . "])(?:\[(.+)\])?)/";
	}

	public function parse(CommandSender $sender, string $arg):array {
		preg_match_all($this->selRegex, $arg, $matches);
		$args = [];
		if(!empty($matches[2])){
			foreach(explode(",", $matches[2][0]) as $arg){
				$arg = explode("=", trim($arg));
				if(count($arg) === 2){
					$args[$arg[0]] = $arg[1];
				}else{
					throw new InvalidCommandSyntaxException("Invalid selector syntax");
				}
			}
		}
		return $this->selectors[$matches[1][0]]->getTargets($sender, $args);
	}

	public function isValid(CommandSender $sender, string $arg) :bool{
		return (bool)preg_match($this->selRegex, $arg);
	}
}