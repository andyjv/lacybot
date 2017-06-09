# LacyBot (@lacy_ebooks)
This is a Telegram Bot that can be added to group chats. It allows users to give each other "Titles".

## The History
In the earlier days of the Lacy Telegram chat, one of the most common questions was "What kind of luggage do you put your fursuit in?" (and variants of that). Lacy had a preferred type of bag, but no one had a link handy. So, I created a bot that would simply look for questions that *appeared* to be asking about luggage. I also added in a fun easter egg: the question "Who's a cub?" would cause the bot to return a pre-determined username.

This had two unintended consequences:
1. People would frequently trigger the bot on purpose
2. Everyone wanted their own "titles"

So I hastily built a way to keep track of "luggage" mentions, to shame people who triggered the bot too much, and a way for people to add titles on their own; and thats the Lacy Bot we have today.

## The Rules
First, thanks for being interested in helping me maintain this bot. Over time, the bot has become a sort of character unto itself. As we make changes to the bot, I'd like to keep to the spirit of the character.

- The bot doesn't need to have perfect grammar
- Titles cannot be deleted
- The bot should be unpredictible
- Users should never be able to see their "queue"
- The question "Who is a Cub?" will always trigger a hardcoded response.

## The Code
The Lacy Bot is a Telegram Bot using the [Webhooks API](https://core.telegram.org/bots/api#getting-updates). As messages enter the telegram group, they are sent to the bot server, which then looks for key words (using RegEx) to decide how to proceed.

### Database
There are two tables: one that keeps track of titles, and another that keeps track of the "luggage" counter.

#### luggage_counter
Keeps track of the "luggage?" trigger
field | description
------|------------
id | auto-increment primary key
date | `DATETIME` field. Records the datetime the record was created
message_id | A unique id assigned to each message by telegram
user_id | A unique id assigned to each user by telegram
username | a `VARCHAR` field storing the username, WITHOUT the @.
record | Seconds since the the previous violation


### lacy_bot
Keeps track of titles
field | description
------|------------
id | auto-increment primary key
setting | Has one of two Values: **who** means this row is a finalized title. If its a **username**, that means this user has that title in the queue.
key | the user the title is assigned to
value | the title
