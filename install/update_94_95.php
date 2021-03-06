<?php
/**
 * ---------------------------------------------------------------------
 * GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2015-2018 Teclib' and contributors.
 *
 * http://glpi-project.org
 *
 * based on GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2003-2014 by the INDEPNET Development Team.
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * GLPI is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 */

/**
 * Update from 9.4.x to 9.5.0
 *
 * @return bool for success (will die for most error)
**/
function update94to95() {
   global $DB, $migration;

   $updateresult     = true;
   $ADDTODISPLAYPREF = [];

   //TRANS: %s is the number of new version
   $migration->displayTitle(sprintf(__('Update to %s'), '9.5.0'));
   $migration->setVersion('9.5.0');

   /** Encrypted FS support  */
   if (!$DB->fieldExists("glpi_items_disks", "encryption_status")) {
      $migration->addField("glpi_items_disks", "encryption_status", "integer", [
            'after'  => "is_dynamic",
            'value'  => 0
         ]
      );
   }

   if (!$DB->fieldExists("glpi_items_disks", "encryption_tool")) {
      $migration->addField("glpi_items_disks", "encryption_tool", "string", [
            'after'  => "encryption_status"
         ]
      );
   }

   if (!$DB->fieldExists("glpi_items_disks", "encryption_algorithm")) {
      $migration->addField("glpi_items_disks", "encryption_algorithm", "string", [
            'after'  => "encryption_tool"
         ]
      );
   }

   if (!$DB->fieldExists("glpi_items_disks", "encryption_type")) {
      $migration->addField("glpi_items_disks", "encryption_type", "string", [
            'after'  => "encryption_algorithm"
         ]
      );
   }
   /** /Encrypted FS support  */

   /** Suppliers restriction */
   if (!$DB->fieldExists('glpi_suppliers', 'is_active')) {
      $migration->addField(
         'glpi_suppliers',
         'is_active',
         'bool',
         ['value' => 0]
      );
      $migration->addKey('glpi_suppliers', 'is_active');
      $migration->addPostQuery(
         $DB->buildUpdate(
            'glpi_suppliers',
            ['is_active' => 1],
            [true]
         )
      );
   }
   /** /Suppliers restriction */

   /** Timezones */
   //User timezone
   if (!$DB->fieldExists('glpi_users', 'timezone')) {
      $migration->addField("glpi_users", "timezone", "varchar(50) DEFAULT NULL");
   }
   $migration->displayWarning("DATETIME fields must be converted to TIMESTAMP for timezones to work. Run bin/console glpi:migration:timestamps");

   // Add a config entry for app timezone setting
   $migration->addConfig(['timezone' => null]);
   /** /Timezones */

   // Fix search Softwares performance
   $migration->dropKey('glpi_softwarelicenses', 'softwares_id_expire_number');
   $migration->addKey('glpi_softwarelicenses', [
      'softwares_id',
      'expire',
      'number'
   ], 'softwares_id_expire_number');

   /** Private supplier followup in glpi_entities */
   if (!$DB->fieldExists('glpi_entities', 'suppliers_as_private')) {
      $migration->addField(
         "glpi_entities",
         "suppliers_as_private",
         "integer",
         [
            'value'     => -2,               // Inherit as default value
            'update'    => 0,                // Not enabled for root entity
            'condition' => 'WHERE `id` = 0'
         ]
      );
   }
   /** /Private supplier followup in glpi_entities */

   /** Entities Custom CSS configuration fields */
   // Add 'custom_css' entities configuration fields
   if (!$DB->fieldExists('glpi_entities', 'enable_custom_css')) {
      $migration->addField(
         'glpi_entities',
         'enable_custom_css',
         'integer',
         [
            'value'     => -2, // Inherit as default value
            'update'    => '0', // Not enabled for root entity
            'condition' => 'WHERE `id` = 0'
         ]
      );
   }
   if (!$DB->fieldExists('glpi_entities', 'custom_css_code')) {
      $migration->addField('glpi_entities', 'custom_css_code', 'text');
   }
   /** /Entities Custom CSS configuration fields */

   /** Clusters */
   if (!$DB->tableExists('glpi_clustertypes')) {
      $query = "CREATE TABLE `glpi_clustertypes` (
         `id` int(11) NOT NULL AUTO_INCREMENT,
         `entities_id` int(11) NOT NULL DEFAULT '0',
         `is_recursive` tinyint(1) NOT NULL DEFAULT '0',
         `name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
         `comment` text COLLATE utf8_unicode_ci,
         `date_creation` timestamp NULL DEFAULT NULL,
         `date_mod` timestamp NULL DEFAULT NULL,
         PRIMARY KEY (`id`),
         KEY `name` (`name`),
         KEY `entities_id` (`entities_id`),
         KEY `is_recursive` (`is_recursive`),
         KEY `date_creation` (`date_creation`),
         KEY `date_mod` (`date_mod`)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
      $DB->queryOrDie($query, "9.5 add table glpi_clustertypes");
   }

   if (!$DB->tableExists('glpi_clusters')) {
      $query = "CREATE TABLE `glpi_clusters` (
         `id` int(11) NOT NULL AUTO_INCREMENT,
         `entities_id` int(11) NOT NULL DEFAULT '0',
         `is_recursive` tinyint(1) NOT NULL DEFAULT '0',
         `name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
         `uuid` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
         `version` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
         `users_id_tech` int(11) NOT NULL DEFAULT '0',
         `groups_id_tech` int(11) NOT NULL DEFAULT '0',
         `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
         `states_id` int(11) NOT NULL DEFAULT '0' COMMENT 'RELATION to states (id)',
         `comment` text COLLATE utf8_unicode_ci,
         `clustertypes_id` int(11) NOT NULL DEFAULT '0',
         `autoupdatesystems_id` int(11) NOT NULL DEFAULT '0',
         `date_mod` timestamp NULL DEFAULT NULL,
         `date_creation` timestamp NULL DEFAULT NULL,
         PRIMARY KEY (`id`),
         KEY `users_id_tech` (`users_id_tech`),
         KEY `group_id_tech` (`groups_id_tech`),
         KEY `is_deleted` (`is_deleted`),
         KEY `states_id` (`states_id`),
         KEY `clustertypes_id` (`clustertypes_id`),
         KEY `autoupdatesystems_id` (`autoupdatesystems_id`),
         KEY `entities_id` (`entities_id`),
         KEY `is_recursive` (`is_recursive`)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
      $DB->queryOrDie($query, "9.5 add table glpi_clusters");
   }

   if (!$DB->tableExists('glpi_items_clusters')) {
      $query = "CREATE TABLE `glpi_items_clusters` (
         `id` int(11) NOT NULL AUTO_INCREMENT,
         `clusters_id` int(11) NOT NULL DEFAULT '0',
         `itemtype` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
         `items_id` int(11) NOT NULL DEFAULT '0',
         PRIMARY KEY (`id`),
         UNIQUE KEY `unicity` (`clusters_id`,`itemtype`,`items_id`),
         KEY `item` (`itemtype`,`items_id`)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
      $DB->queryOrDie($query, "9.5 add table glpi_items_clusters");
   }

   $migration->addField('glpi_states', 'is_visible_cluster', 'bool', [
      'value' => 1,
      'after' => 'is_visible_pdu'
   ]);
   $migration->addKey('glpi_states', 'is_visible_cluster');

   $migration->addRight('cluster', ALLSTANDARDRIGHT);

   $ADDTODISPLAYPREF['cluster'] = [31, 19];
   /** /Clusters */
   /** ITIL templates */
   //rename tables
   foreach ([
      'glpi_tickettemplates',
      'glpi_tickettemplatepredefinedfields',
      'glpi_tickettemplatemandatoryfields',
      'glpi_tickettemplatehiddenfields'
   ] as $table) {
      if ($DB->tableExists($table)) {
         $migration->renameTable($table, str_replace('ticket', 'itil', $table));
      }
   }
   //rename fkeys
   foreach ([
      'glpi_entities'                     => 'tickettemplates_id',
      'glpi_itilcategories'               => 'tickettemplates_id_incident',
      'glpi_itilcategories'               => 'tickettemplates_id_demand',
      'glpi_profiles'                     => 'tickettemplates_id',
      'glpi_ticketrecurrents'             => 'tickettemplates_id',
      'glpi_itiltemplatehiddenfields'     => 'tickettemplates_id',
      'glpi_itiltemplatemandatoryfields'  => 'tickettemplates_id',
      'glpi_itiltemplatepredefinedfields' => 'tickettemplates_id'
   ] as $table => $field) {
      if ($DB->fieldExists($table, $field)) {
         $migration->changeField($table, $field, str_replace('ticket', 'itil', $field), 'integer');
      }
   }
   //rename profilerights values
   $migration->addPostQuery(
      $DB->buildUpdate(
         'glpi_profilerights',
         ['name' => 'itiltemplate'],
         ['name' => 'tickettemplate']
      )
   );
   /** /ITIL templates */

   /** add templates for followups */
   if (!$DB->tableExists('glpi_itilfollowuptemplates')) {
      $query = "CREATE TABLE `glpi_itilfollowuptemplates` (
         `id`              INT(11) NOT NULL AUTO_INCREMENT,
         `date_creation`   TIMESTAMP NULL DEFAULT NULL,
         `date_mod`        TIMESTAMP NULL DEFAULT NULL,
         `entities_id`     INT(11) NOT NULL DEFAULT '0',
         `is_recursive`    TINYINT(1) NOT NULL DEFAULT '0',
         `name`            VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
         `content`         TEXT NULL COLLATE 'utf8_unicode_ci',
         `requesttypes_id` INT(11) NOT NULL DEFAULT '0',
         `is_private`      TINYINT(1) NOT NULL DEFAULT '0',
         `comment`         TEXT NULL COLLATE 'utf8_unicode_ci',
         PRIMARY KEY (`id`),
         INDEX `name` (`name`),
         INDEX `is_recursive` (`is_recursive`),
         INDEX `requesttypes_id` (`requesttypes_id`),
         INDEX `entities_id` (`entities_id`),
         INDEX `date_mod` (`date_mod`),
         INDEX `date_creation` (`date_creation`),
         INDEX `is_private` (`is_private`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, "add table glpi_itilfollowuptemplates");
   }

   // ************ Keep it at the end **************
   foreach ($ADDTODISPLAYPREF as $type => $tab) {
      $rank = 1;
      foreach ($tab as $newval) {
         $DB->updateOrInsert("glpi_displaypreferences", [
            'rank'      => $rank++
         ], [
            'users_id'  => "0",
            'itemtype'  => $type,
            'num'       => $newval,
         ]);
      }
   }

   $migration->executeMigration();

   return $updateresult;
}
