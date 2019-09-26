<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\selection\info;

use CortexPE\Commando\args\BaseArgument;
use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseCommand;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat as TF;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\task\action\CountAction;
use xenialdan\MagicWE2\task\AsyncActionTask;

class CountCommand extends BaseCommand
{

    /**
     * This is where all the arguments, permissions, sub-commands, etc would be registered
     * @throws \CortexPE\Commando\exception\ArgumentOrderException
     */
    protected function prepare(): void
    {
        $this->registerArgument(0, new RawStringArgument("blocks", true));
        $this->registerArgument(1, new TextArgument("flags", true));
        $this->setPermission("we.command.selection.info.count");
    }

    /**
     * @param CommandSender $sender
     * @param string $aliasUsed
     * @param BaseArgument[] $args
     */
    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $lang = Loader::getInstance()->getLanguage();
        if (!$sender instanceof Player) {
            $sender->sendMessage(TF::RED . $lang->translateString('error.runingame'));
            return;
        }
        /** @var Player $sender */
        try {
            $error = false;
            if (!empty($args["blocks"])) {
                $messages = [];
                API::blockParser(($filterBlocks = strval($args["blocks"])), $messages, $error);
                foreach ($messages as $message) {
                    $sender->sendMessage($message);
                }
            } else $filterBlocks = "";
            if (!$error) {
                $session = SessionHelper::getUserSession($sender);
                if (is_null($session)) {
                    throw new \Exception(Loader::getInstance()->getLanguage()->translateString('error.nosession', [Loader::getInstance()->getName()]));
                }
                $selection = $session->getLatestSelection();
                if (is_null($selection)) {
                    throw new \Exception(Loader::getInstance()->getLanguage()->translateString('error.noselection'));
                }
                if (!$selection->isValid()) {
                    throw new \Exception(Loader::getInstance()->getLanguage()->translateString('error.selectioninvalid'));
                }
                if ($selection->getLevel() !== $sender->getLevel()) {
                    $session->sendMessage(TF::GOLD . Loader::getInstance()->getLanguage()->translateString('warning.differentlevel'));
                }
                Server::getInstance()->getAsyncPool()->submitTask(
                    new AsyncActionTask(
                        $session->getUUID(),
                        $selection,
                        new CountAction(),
                        $selection->getShape()->getTouchedChunks($selection->getLevel()),
                        "",
                        $filterBlocks
                    )
                );
            } else {
                throw new \InvalidArgumentException("Could not count the selected blocks");
            }
        } catch (\Exception $error) {
            $sender->sendMessage(Loader::PREFIX . TF::RED . Loader::getInstance()->getLanguage()->translateString('error.command-error'));
            $sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
            $sender->sendMessage($this->getUsage());
        } catch (\ArgumentCountError $error) {
            $sender->sendMessage(Loader::PREFIX . TF::RED . Loader::getInstance()->getLanguage()->translateString('error.command-error'));
            $sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
            $sender->sendMessage($this->getUsage());
        } catch (\Error $error) {
            Loader::getInstance()->getLogger()->logException($error);
            $sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
        }
    }
}
