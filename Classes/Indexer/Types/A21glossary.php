<?php
namespace TeaminmediasPluswerk\KeSearch\Indexer\Types;

use TeaminmediasPluswerk\KeSearch\Indexer\IndexerBase;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/***************************************************************
 *  Copyright notice
 *  (c) 2014 Christian Bülter (kennziffer.com) <buelter@kennziffer.com>
 *  All rights reserved
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Indexer for a21_glossary extesnion
 *
 * @author    Andreas Kiefer (kennziffer.com) <kiefer@kennziffer.com>
 * @author    Stefan Frömken
 * @author    Christian Bülter (kennziffer.com) <buelter@kennziffer.com>
 * @package    TYPO3
 * @subpackage    tx_kesearch
 */
class A21glossary extends IndexerBase
{

    /**
     * Initializes indexer for a21glossary
     */
    public function __construct($pObj)
    {
        parent::__construct($pObj);
    }

    /**
     * This function was called from indexer object and saves content to index table
     * @return string content which will be displayed in backend
     */
    public function startIndexing()
    {
        $content = '';
        $table = 'tx_a21glossary_main';

        // get the pages from where to index the news
        $indexPids = $this->getPidList(
            $this->indexerConfig['startingpoints_recursive'],
            $this->indexerConfig['sysfolder'],
            $table
        );

        // add the tags of the parent page
        if ($this->indexerConfig['index_use_page_tags']) {
            $this->pageRecords = $this->getPageRecords($indexPids);
            $this->addTagsToRecords($indexPids);
        }

        // get all the glossary records to index, don't index hidden or
        // deleted glossary records, BUT  get the records with frontend user group
        // access restrictions or time (start / stop) restrictions.
        // Copy those restrictions to the index.
        $fields = '*';
        $where = 'pid IN (' . implode(',', $indexPids) . ') ';
        $where .= BackendUtility::BEenableFields($table);
        $where .= BackendUtility::deleteClause($table);

        $res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields, $table, $where);
        $indexedRecordsCounter = 0;
        $resCount = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
        if ($resCount) {
            while (($record = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))) {
                // compile the information which should go into the index:
                // short, shortcut, longversion, shorttype, description, link
                $title = strip_tags($record['short']);
                $abstract = strip_tags($record['longversion']);
                $fullContent = strip_tags($record['shortcut']
                    . "\n" . $record['longversion']
                    . "\n" . $record['description']
                    . "\n" . $record['link']);

                // compile params for single view, example:
                // index.php?id=16&tx_a21glossary[uid]=71&cHash=9f9368211d8ae742a8d3ad29c4f0a308
                $paramsSingleView = array();
                $paramsSingleView['tx_a21glossary']['uid'] = $record['uid'];
                $params = rawurldecode('&' . http_build_query($paramsSingleView, null, '&'));

                // add tags from pages
                if ($this->indexerConfig['index_use_page_tags']) {
                    $tags = $this->pageRecords[intval($record['pid'])]['tags'];
                } else {
                    $tags = '';
                }

                // make it possible to modify the indexerConfig via hook
                $indexerConfig = $this->indexerConfig;

                // set additional fields
                $additionalFields = array();
                $additionalFields['orig_uid'] = $record['uid'];
                $additionalFields['orig_pid'] = $record['pid'];
                $additionalFields['sortdate'] = $record['crdate'];

                // hook for custom modifications of the indexed data, e.g. the tags
                if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifya21glossaryIndexEntry'])) {
                    foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifya21glossaryIndexEntry'] as
                             $_classRef) {
                        $_procObj = &GeneralUtility::makeInstance($_classRef);
                        $_procObj->modifya21glossaryIndexEntry(
                            $title,
                            $abstract,
                            $fullContent,
                            $params,
                            $tags,
                            $record,
                            $additionalFields,
                            $indexerConfig,
                            $this
                        );
                    }
                }

                // store this record to the index
                $this->pObj->storeInIndex(
                    $indexerConfig['storagepid'],    // storage PID
                    $title,                         // page title
                    'a21glossary',                    // content type
                    $indexerConfig['targetpid'],    // target PID: where is the single view?
                    $fullContent,                   // indexed content, includes the title (linebreak after title)
                    $tags,                          // tags
                    $params,                        // typolink params for singleview
                    $abstract,                      // abstract
                    $record['sys_language_uid'],    // language uid
                    $record['starttime'],        // starttime
                    $record['endtime'],            // endtime
                    $record['fe_group'],            // fe_group
                    false,                          // debug only?
                    $additionalFields               // additional fields added by hooks
                );
                $indexedRecordsCounter++;
            }
        }
        return $indexedRecordsCounter . ' glossary records have been indexed.';
    }
}
