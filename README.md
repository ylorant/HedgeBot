# HedgeBot

*Hedgebot, chatting around at the speed of sound*

Hedgebot is a Twitch Bot written in PHP, aimed at a server-side use. It aims to provide a
flexible and powerful setup for streamers and communities to improve their chatrooms.

It requires at least PHP 7.0 to be used.

## What it does

Core functions :

- Connects to Twitch IRC servers one or multiple times (multi-server setup)
- Connects to multiple channels
- It has a flexible plugin management system, based on an event system
- Handles disconnections
- Basic Twitch API v3 handling
- JSON-RPC based API for remote querying (web-based admin ?)
- Internal API for quick and easy access to bot functions from plugins
- Documentation generator for plugin commands (outputs Markdown).

Plugins :

- Announcements : Automatically say things on your chat every once in a while
- CustomCommands : Make custom commands that prints messages
- Quotes : Handles a quote manager
- Currency : Handles a currency/money system on the chat
- BlackJack : Handles a blackjack game manager
- TestManager : development-oriented plugin that allows to test how other plugins are supposed to work.

## What is planned to do

- [IN PROGRESS] Spam regulation plugin, timing out users for unwanted words in messages, links, walltexts and/or other things
- Auto hosting, allowing your channel to automatically host streaming people based on a list, and for a set amount of time
- Donation alerts, with StreamLabs API
- Update Twitch API integration to v5
- Cooldown feature, as a core feature (allowing cooldown per user type and per command).
- Logger core feature, allowing logging actions from every plugin
- Statistics plugin, gathering real time stats from chat
- Discord API integration
- Horaro API integration
- Raffle plugin
- [IN PROGRESS] Web admin
- Update all plugins' comments to fit Documentor standards (like on the Currency plugin).

## I want it !

If you want to install it, you can follow the [wiki page](https://github.com/ylorant/HedgeBot/wiki/Installing-HedgeBot)
detailing all the instructions.

## How can I help ?

If you want to help improving this bot, you can do it in the following ways :

- Developing plugins. If you want to merge them in the main tree, drop a Pull Request :)
- Documenting things. 
- Testing. Tests are done to ensure that the plugins work properly, but nothing beats a real field test.

For all of these things, if you want to discuss about it, feel free to contact me on my Twitter : [@linkboss](https://twitter.com/linkboss).
If the project grows a bit, other means of discussion will be put in place.