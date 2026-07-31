<?php

namespace vision\services\chat;

use pocketmine\player\chat\ChatFormatter;
use pocketmine\utils\TextFormat;

final class CustomChatFormatter implements ChatFormatter {
    public function __construct(private readonly string $prefix) {}

    public function format(string $username, string $message): string {
        return $this->prefix . TextFormat::clean($message);
    }
}
