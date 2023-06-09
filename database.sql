SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `lottery_channels` (
  `id` int NOT NULL,
  `userId` varchar(30) NOT NULL,
  `chanName` varchar(150) NOT NULL,
  `chanId` varchar(40) NOT NULL,
  `chatName` text,
  `submited` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `lottery_list` (
  `id` int NOT NULL,
  `title` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `description` varchar(2500) DEFAULT NULL,
  `image` longblob,
  `dateStart` datetime DEFAULT NULL,
  `dateStop` datetime DEFAULT NULL,
  `countWinners` int DEFAULT NULL,
  `connectedChannels` text,
  `mId` varchar(10) DEFAULT NULL,
  `started` int NOT NULL DEFAULT '0',
  `finished` int DEFAULT '0',
  `del` int DEFAULT '0',
  `author` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `lottery_members` (
  `id` int NOT NULL,
  `lotteryId` varchar(30) DEFAULT NULL,
  `userId` varchar(30) DEFAULT NULL,
  `login` varchar(150) DEFAULT NULL,
  `firstName` varchar(150) DEFAULT NULL,
  `lastName` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `channel` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `lottery_users` (
  `id` int NOT NULL,
  `userId` varchar(30) NOT NULL,
  `userName` varchar(150) NOT NULL,
  `userType` int NOT NULL,
  `userRegister` datetime NOT NULL,
  `mode` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE `lottery_channels`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `lottery_list`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `lottery_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `mark` (`lotteryId`,`userId`,`channel`);

ALTER TABLE `lottery_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `userId` (`userId`);

ALTER TABLE `lottery_channels`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `lottery_list`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `lottery_members`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `lottery_users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;
COMMIT;