set foreign_key_checks = 0;
set sql_mode = 'no_auto_value_on_zero';
set autocommit = 0;
start transaction;
set time_zone = '+00:00';

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- Table structure for table `sys_access`

drop table if exists `sys_access`;
create table `sys_access` 
(
    `id` int(11) not null auto_increment,
    `bind` varchar(50) default null,
    `user_id` int(11) not null default 0,
    `group_id` int(11) not null default 0,
    `created_by` int(5) not null default 0,
    `created_at` datetime default null,
    `updated_by` int(5) not null default 0,
    `updated_at` datetime default null,
    `deleted_by` int(5) not null default 0,
    `deleted_at` datetime default null,
    `deleted` tinyint(1) not null default 0,
    primary key (`id`)
) 
engine = innodb default charset = utf8 comment = 'ENABLE_AUDIT';

insert into `sys_access` 
(`id`, `bind`, `user_id`, `group_id`) values

(1, 'system.access', 1, 1),
(2, 'system.access', 1, 2);

-- --------------------------------------------------------
-- Table structure for table `sys_config`

drop table if exists `sys_config`;
create table `sys_config` 
(
    `id` int(11) not null auto_increment,
    `bind` varchar(50) default null,
    `lang` varchar(10) not null default 'en',
    `variable` varchar(250) default null,
    `value` text,
    `created_by` int(5) not null default 0,
    `created_at` datetime default null,
    `updated_by` int(5) not null default 0,
    `updated_at` datetime default null,
    `deleted_by` int(5) not null default 0,
    `deleted_at` datetime default null,
    `deleted` tinyint(1) not null default 0,
    primary key (`id`)
) 
engine = innodb default charset = utf8;

insert into `sys_config` 
(`id`, `bind`, `lang`, `variable`, `value`, `created_by`, `created_at`, `updated_by`, `updated_at`, `deleted_by`, `deleted_at`, `deleted`) values

(1, 'system.config', 'en', 'title', 'f7 speed site', 0, null, 0, null, 0, null, 0);

-- --------------------------------------------------------
-- Table structure for table `sys_groups`

drop table if exists `sys_groups`;
create table `sys_groups` 
(
    `id` int(11) not null auto_increment,
    `bind` varchar(50) default null,
    `name` varchar(50) default null,
    `cms_access` text,
    `app_access` text,
    `created_by` int(5) not null default 0,
    `created_at` datetime default null,
    `updated_by` int(5) not null default 0,
    `updated_at` datetime default null,
    `deleted_by` int(5) not null default 0,
    `deleted_at` datetime default null,
    `deleted` tinyint(1) not null default 0,
    primary key (`id`)
) 
engine = innodb default charset = utf8 comment = 'ENABLE_AUDIT';

insert into `sys_groups` 
(`id`, `bind`, `name`, `cms_access`, `app_access`) values

(1, 'system.groups', 'CMS Super Admin', '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'),
(2, 'system.groups', 'Application Managers', 'gJ0uEmFxrHve5+rVUyxKWRA9releHTSXQfqEOtHNnkcoJtx1Fq8a/tHtDWEuI0sci8/SisliKJbhMuD53PQGMg==', 'elLMGEy/aJYnQWzQyZb/BcYSAZHP6LD6x6zDFZTFqVxl/w8QEpg4QVzqFF9ucme0s1nubllo8oQ3zTu4T1zyRrUsIUOgwEM3qqiK34ayHvxibhYueKS/NUtZZPixrC7yNn3Gyms2E4o21WoH4o1hpmgjQsEewcpq4HTS3/vYM1ID26iAiWSHhfvLF7n9K+ZKGw/Qpb+QWfZjjHUhojg8zgW9oJc06thSqBCINNNxOStdbZhyqoVHoph9ZJqUoaOUoDYR+Nq2CU7ET0IkYOv4fEa7r4Xo13PcEzROI/cQT0NTU/j11vjxU3Y1kg44lOXO5lRB4hmedx3SFCGZNUO6LmCyIilqQBTyEdxrZrIgRRzZO4/JJG2lDdKXkNc7EpGzKM+AU89r8aYL76kpUeOjCcbm1jfQmBEWnPekil6KrPk9/lJXQ2AHhDM1BbUAa1f+NBeuLZGOVBPz/lSKu22vFw==');

-- --------------------------------------------------------
-- Table structure for table `sys_users`

drop table if exists `sys_users`;
create table `sys_users` (
    `id` int(11) not null auto_increment,
    `bind` varchar(50) not null,
    `username` varchar(50) not null default '',
    `fullname` varchar(100) default null default '',
    `email` varchar(100),
    `password` varchar(32) default '21232f297a57a5a743894a0e4a801fc3',
    `lang` varchar(5) not null default 'en',
    `active` tinyint(1) not null default 1,
    primary key (`id`)
) 
engine = innodb default charset = utf8;

insert into `sys_users` 
(`id`, `bind`, `username`, `fullname`) values

(1, 'system.users', 'admin', 'Admin User');

-- --------------------------------------------------------
-- Indexes for dumped tables

alter table `sys_access`
    add key `fk_sys_access_sys_users` (`user_id`),
    add key `fk_sys_access_sys_groups` (`group_id`);

-- --------------------------------------------------------
-- Constraints for dumped tables

alter table `sys_access`
    add constraint `fk_sys_access_sys_groups` foreign key (`group_id`) references `sys_groups` (`id`) on delete cascade on update no action,
    add constraint `fk_sys_access_sys_users` foreign key (`user_id`) references `sys_users` (`id`) on delete cascade on update no action;

set foreign_key_checks = 1;
commit;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;