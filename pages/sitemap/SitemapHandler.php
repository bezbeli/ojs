<?php

/**
 * @file pages/sitemap/SitemapHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SitemapHandler
 *
 * @ingroup pages_sitemap
 *
 * @brief Produce a sitemap in XML format for submitting to search engines.
 */

namespace APP\pages\sitemap;

use APP\core\Request;
use APP\facades\Repo;
use APP\issue\Collector;
use APP\submission\Submission;
use DOMDocument;
use PKP\pages\sitemap\PKPSitemapHandler;
use PKP\plugins\Hook;

class SitemapHandler extends PKPSitemapHandler
{
    /**
     * @copydoc PKPSitemapHandler::index()
     */
    public function index($args, $request)
    {
        if (!$request->getContext() && ($args[0] ?? null) === 'site') {
            $doc = $this->_createSiteSitemap($request);
            header('Content-Type: application/xml');
            header('Cache-Control: private');
            header('Content-Disposition: inline; filename=sitemap_site.xml');
            echo $doc->saveXml();
            return;
        }

        parent::index($args, $request);
    }

    /**
     * Include a sitemap for the multi-journal site index in the sitemap index.
     *
     * @copydoc PKPSitemapHandler::_createSitemapIndex()
     */
    public function _createSitemapIndex($request)
    {
        $doc = parent::_createSitemapIndex($request);
        $root = $doc->documentElement;

        $siteSitemapUrl = $request->url('index', 'sitemap', 'site');
        $sitemap = $doc->createElement('sitemap');
        $sitemap->appendChild($doc->createElement('loc', htmlspecialchars($siteSitemapUrl, ENT_COMPAT, 'UTF-8')));
        $root->appendChild($sitemap);

        return $doc;
    }

    /**
     * Sitemap URLs for the site (multi-journal) landing page.
     */
    protected function _createSiteSitemap(Request $request): DOMDocument
    {
        $doc = new DOMDocument('1.0', 'utf-8');
        $root = $doc->createElement('urlset');
        $root->setAttribute('xmlns', SITEMAP_XSD_URL);
        $root->appendChild($this->_createUrlTree($doc, $request->url('index', 'index')));
        $doc->appendChild($root);

        return $doc;
    }

    /**
     * @copydoc PKPSitemapHandler_createContextSitemap()
     *
     * @hook SitemapHandler::createJournalSitemap [[&$doc]]
     */
    public function _createContextSitemap($request)
    {
        $doc = parent::_createContextSitemap($request);
        $root = $doc->documentElement;

        $journal = $request->getJournal();
        $journalId = $journal->getId();

        // Search
        $root->appendChild($this->_createUrlTree($doc, $request->url($journal->getPath(), 'search')));
        // Issues
        if ($journal->getData('publishingMode') != \APP\journal\Journal::PUBLISHING_MODE_NONE) {
            $root->appendChild($this->_createUrlTree($doc, $request->url($journal->getPath(), 'issue', 'current')));
            $root->appendChild($this->_createUrlTree($doc, $request->url($journal->getPath(), 'issue', 'archive')));
            $publishedIssues = Repo::issue()->getCollector()
                ->filterByContextIds([$journalId])
                ->filterByPublished(true)
                ->orderBy(Collector::ORDERBY_PUBLISHED_ISSUES)
                ->getMany();
            foreach ($publishedIssues as $issue) {
                $root->appendChild($this->_createUrlTree($doc, $request->url($journal->getPath(), 'issue', 'view', [$issue->getId()])));
                // Articles for issue
                $submissions = Repo::submission()
                    ->getCollector()
                    ->filterByContextIds([$journal->getId()])
                    ->filterByIssueIds([$issue->getId()])
                    ->filterByStatus([Submission::STATUS_PUBLISHED])
                    ->getMany();

                foreach ($submissions as $submission) {
                    // Abstract
                    $root->appendChild($this->_createUrlTree($doc, $request->url($journal->getPath(), 'article', 'view', [$submission->getBestId()])));
                    // Galley files
                    $galleys = Repo::galley()
                        ->getCollector()
                        ->filterByPublicationIds([($submission->getCurrentPublication()->getId())])
                        ->getMany();

                    foreach ($galleys as $galley) {
                        $root->appendChild($this->_createUrlTree($doc, $request->url($journal->getPath(), 'article', 'view', [$submission->getBestId(), $galley->getBestGalleyId()])));
                    }
                }
            }
        }

        $doc->appendChild($root);

        // Enable plugins to change the sitemap
        Hook::call('SitemapHandler::createJournalSitemap', [&$doc]);

        return $doc;
    }
}
