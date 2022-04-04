<h1>Commando<img src="https://raw.githubusercontent.com/CortexPE/Commando/master/commando.png" height="64" width="64" align="left"></img>&nbsp;<img src="https://poggit.pmmp.io/ci.shield/CortexPE/Commando/~"></img></h1>
<br />

A PocketMine-MP Virion for easier implementation of dynamic commands, including support for Minecraft: Bedrock Edition argument listing aimed for both the end users and the plugin developers.

# Usage:
Installation is easy, you may get a compiled phar [here](https://poggit.pmmp.io/ci/CortexPE/Commando/~), integrate the virion itself into your plugin or you could also use it as a composer library by running the command below:

`composer require cortexpe/commando`

This virion is purely object oriented. So, to use it you'll have to extend the `BaseCommand` object, import the `PacketHooker` object and the optional objects for subcommands and arguments (whenever necessary).

For PocketMine-MP API 4, you will need to include [Muqsit/SimplePacketHandler](https://github.com/Muqsit/SimplePacketHandler) in your dependencies.

# Why is this necessary?
The virion provides an easy way to verify user input, convert user input, and for making sure that our arguments are the type that we expect it to.

On the plus side, it also provides the argument list for the client to recognize making it easy to use the command without remembering the order of arguments.

Because not only MC: Bedrock can use the commands, I've also implemented command usage pre-generation for ease of use with the console as well.

This also provides an easy to use API for lessening boilerplate code while adding more functionality and verbosity (error codes, and error lists, and sending usage messages).

It is structured in a similar way to the legacy PocketMine commands for ease of migration from an older codebase.

**Upon the time of writing this readme file, This virion will be used on [Hierarchy](https://github.com/CortexPE/Hierarchy) for the command implementation clean-up**

## Basic Usage:

***NOTE: Other miscellaneous functions can be indexed within your IDEs or by reading the source code. This is only the basic usage of the virion, it does not show every aspect of it as that'd be too long to document.***

### Create your command class
In our command class, we need to extend `BaseCommand` and implement its required methods to use all of Commando's features.
```php
<?php

use CortexPE\Commando\BaseCommand;
use pocketmine\command\CommandSender;

class MyCommand extends BaseCommand {
	protected function prepare(): void {
		// This is where we'll register our arguments and subcommands
	}
	
	public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
		// This is where the processing will occur if it's NOT handled by other subcommands
	}
}
```

### Register the arguments
If we register arguments, we need to import and use / extend (if needed) the provided argument objects.
```php
use CortexPE\Commando\args\RawStringArgument;

	protected function prepare(): void {
		// $this->registerArgument(position, argument object (name, isOptional));
		$this->registerArgument(0, new RawStringArgument("name", true));
	}
```

### Handling our arguments
The arguments passed on our `onRun` method will be mapped by `name => value` this makes it easy to understand which argument is which, instead of using numeric indices. It is also guaranteed that the arguments passed will be the declared type that we've set.
```php
	public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
		if(isset($args["name"])){
			$sender->sendMessage("Hello, " . $args["name"] . "!");
		} else {
			$this->sendUsage();
		}
	}
```

### Registering the `PacketHooker` for vanilla command arguments
The `PacketHooker` listener is required for us to be able to inject data to the `AvailableCommandsPacket` the server sends.
```php
use CortexPE\Commando\PacketHooker;

// onEnable:
	if(!PacketHooker::isRegistered()) {
		PacketHooker::register($this);
	}
```

### Registering the command from a plugin
Once we've constructed our command with our arguments and subcommands, we can now register our command to PocketMine's command map, to be available to our users.
```php
// onEnable:
$this->getServer()->getCommandMap()->register("myplugin", new MyCommand($this, "greet", "Make the server greet you!"));
```
The only difference with using this framework is that you don't need to set the usage message, as they are pre-generated after all the arguments have been registered.

### SubCommands
Subcommands work the same way as regular commands, the only difference is that they're registered on the parent command with `BaseCommand->registerSubCommand()` having their own set of arguments and own usage message.

### Error messages
The virion provides default error messages for user input errors regarding the arguments given. It also provides a way to register your own error message formats for the sake of customizability.
```php
$cmdCtx->setErrorFormat($errorCode, $format);
// Arrays can be passed on `BaseCommand->setErrorFormats()` to bulk-set other error messages
```
The error messages are sent in bulk to the users to let them know what parts are wrong with their input, not having to do trial-and-error.
*A current limitation is that, you cannot register your own error messages with other error codes.*

-----
**This framework was made with :heart: by CortexPE, Enjoy!~ :3**
