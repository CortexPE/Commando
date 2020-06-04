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

namespace CortexPE\Commando\traits;


use CortexPE\Commando\args\BaseArgument;
use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\exception\ArgumentOrderException;
use pocketmine\command\CommandSender;
use function array_slice;
use function count;
use function implode;
use function is_array;
use function usort;

trait ArgumentableTrait {
	/** @var BaseArgument[][] */
	private $argumentList = []; // [argumentPosition => [...possible BaseArgument(s)]]
	/** @var bool[] */
	private $requiredArgumentCount = [];

	/**
	 * This is where all the arguments, permissions, sub-commands, etc would be registered
	 */
	abstract protected function prepare(): void;

	/**
	 * @param int          $position
	 * @param BaseArgument $argument
	 *
	 * @throws ArgumentOrderException
	 */
	public function registerArgument(int $position, BaseArgument $argument): void {
		if($position < 0) {
			throw new ArgumentOrderException("You cannot register arguments at negative positions");
		}
		if($position > 0 && !isset($this->argumentList[$position - 1])) {
			throw new ArgumentOrderException("There were no arguments before $position");
		}
		foreach($this->argumentList[$position - 1] ?? [] as $arg) {
			if($arg instanceof TextArgument) {
				throw new ArgumentOrderException("No other arguments can be registered after a TextArgument");
			}
			if($arg->isOptional() && !$argument->isOptional()){
				throw new ArgumentOrderException("You cannot register a required argument after an optional argument");
			}
		}
		$this->argumentList[$position][] = $argument;
		if(!$argument->isOptional()) {
			$this->requiredArgumentCount[$position] = true;
		}
	}

	public function parseArguments(array $rawArgs, CommandSender $sender): array {
		$return = [
			"arguments" => [],
			"errors" => []
		];
		// try parsing arguments
		$required = count($this->requiredArgumentCount);
		if(!$this->hasArguments() && count($rawArgs) > 0) { // doesnt take args but sender gives args anyways
			$return["errors"][] = [
				"code" => BaseCommand::ERR_NO_ARGUMENTS,
				"data" => []
			];
		}
		$offset = 0;
		if(count($rawArgs) > 0) {
			foreach($this->argumentList as $pos => $possibleArguments) {
				// try the one that spans more first... before the others
				usort($possibleArguments, function (BaseArgument $a, BaseArgument $b): int {
					if($a->getSpanLength() === PHP_INT_MAX) { // if it takes unlimited arguments, pull it down
						return 1;
					}

					return -1;
				});
				$parsed = false;
				$optional = true;
				foreach($possibleArguments as $argument) {
					$arg = trim(implode(" ", array_slice($rawArgs, $offset, ($len = $argument->getSpanLength()))));
					if(!$argument->isOptional()) {
						$optional = false;
					}
					if($arg !== "" && $argument->canParse($arg, $sender)) {
						$k = $argument->getName();
						$result = (clone $argument)->parse($arg, $sender);
						if(isset($return["arguments"][$k]) && !is_array($return["arguments"][$k])) {
							$old = $return["arguments"][$k];
							unset($return["arguments"][$k]);
							$return["arguments"][$k] = [$old];
							$return["arguments"][$k][] = $result;
						} else {
							$return["arguments"][$k] = $result;
						}
						if(!$optional) {
							$required--;
						}
						$offset += $len;
						$parsed = true;
						break;
					}
					if($offset > count($rawArgs)) {
						break; // we've reached the end of the argument list the user passed
					}
				}
				if(!$parsed && !($optional && empty($arg))) { // we tried every other possible argument type, none was satisfied
					$return["errors"][] = [
						"code" => BaseCommand::ERR_INVALID_ARG_VALUE,
						"data" => [
							"value" => $rawArgs[$offset] ?? "",
							"position" => $pos + 1
						]
					];

					return $return; // let's break it here.
				}
			}
		}
		if($offset < count($rawArgs)) { // this means that the arguments our user sent is more than the needed amount
			$return["errors"][] = [
				"code" => BaseCommand::ERR_TOO_MANY_ARGUMENTS,
				"data" => []
			];
		}
		if($required > 0) {// We still have more unfilled required arguments
			$return["errors"][] = [
				"code" => BaseCommand::ERR_INSUFFICIENT_ARGUMENTS,
				"data" => []
			];
		}

		return $return;
	}

	public function generateUsageMessage(): string {
		$msg = $this->getName() . " ";
		$args = [];
		foreach($this->argumentList as $pos => $arguments) {
			$hasOptional = false;
			$names = [];
			foreach($arguments as $k => $argument) {
				$names[] = $argument->getName() . ":" . $argument->getTypeName();
				if($argument->isOptional()) {
					$hasOptional = true;
				}
			}
			$names = implode("|", $names);
			if($hasOptional) {
				$args[] = "[" . $names . "]";
			} else {
				$args[] = "<" . $names . ">";
			}
		}
		$msg .= implode(" ", $args);

		return $msg;
	}

	public function hasArguments(): bool {
		return !empty($this->argumentList);
	}

	public function hasRequiredArguments(): bool {
		foreach($this->argumentList as $arguments) {
			foreach($arguments as $argument) {
				if(!$argument->isOptional()) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @return BaseArgument[][]
	 */
	public function getArgumentList(): array {
		return $this->argumentList;
	}
}
