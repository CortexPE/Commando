<?php

/***
 *    ___                                          _
 *   / __\___  _ __ ___  _ __ ___   __ _ _ __   __| | ___
 *  / /  / _ \| '_ ` _ \| '_ ` _ \ / _` | '_ \ / _` |/ _ \
 * / /__| (_) | | | | | | | | | | | (_| | | | | (_| | (_) |
 * \____/\___/|_| |_| |_|_| |_| |_|\__,_|_| |_|\__,_|\___/
 *
 * Commando - A Command Framework virion for PocketMine-MP
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * Written by @CortexPE <https://CortexPE.xyz>
 *
 */
declare(strict_types=1);

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