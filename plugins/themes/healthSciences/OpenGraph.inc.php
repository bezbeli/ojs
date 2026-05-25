<?php

/**
 * @file plugins/themes/healthSciences/OpenGraph.inc.php
 *
 * Open Graph and Twitter Card meta tags for social sharing.
 */

use APP\core\Application;
use APP\file\PublicFileManager;
use APP\template\TemplateManager;
use PKP\core\PKPApplication;
use PKP\i18n\LocaleConversion;

class HealthSciencesOpenGraph
{
    /**
     * Inject Open Graph tags on article pages.
     *
     * @return bool
     */
    public static function addArticleTags($hookName, $args)
    {
        $request = $args[0];
        $issue = $args[1];
        $article = $args[2];
        $publication = $args[3];
        $requestArgs = $request->getRequestedArgs();

        if (count($requestArgs) > 1 && $requestArgs[1] === 'version') {
            return false;
        }

        $journal = $request->getContext();
        if (!$journal) {
            return false;
        }

        $templateMgr = TemplateManager::getManager($request);
        $publicationLocale = $publication->getData('locale');
        $title = $publication->getLocalizedFullTitle($publicationLocale);
        $description = self::truncateDescription((string) ($publication->getLocalizedData('abstract', $publicationLocale) ?: ''));
        $articleBestId = strlen($urlPath = (string) $publication->getData('urlPath')) ? $urlPath : $article->getId();
        $url = $request->getDispatcher()->url(
            $request,
            PKPApplication::ROUTE_PAGE,
            null,
            'article',
            'view',
            [$articleBestId],
            urlLocaleForPage: ''
        );

        $image = $publication->getLocalizedCoverImageUrl($journal->getId());
        if (!$image && $issue) {
            $image = $issue->getLocalizedCoverImageUrl();
        }
        if (!$image) {
            $image = self::getJournalImageUrl($request, $journal);
        }

        self::outputTags($templateMgr, [
            'title' => $title,
            'description' => $description,
            'url' => $url,
            'image' => $image,
            'type' => 'article',
            'siteName' => $journal->getLocalizedName(),
            'locale' => LocaleConversion::toBcp47($publicationLocale),
        ]);

        return false;
    }

    /**
     * Inject Open Graph tags on journal and issue pages.
     *
     * @return bool
     */
    public static function addTemplateTags($hookName, $args)
    {
        $templateMgr = $args[0];
        $template = $args[1];
        $request = Application::get()->getRequest();
        $journal = $request->getContext();

        if (!$journal) {
            return false;
        }

        switch ($template) {
            case 'frontend/pages/indexJournal.tpl':
                self::addJournalTags($templateMgr, $request, $journal);
                break;
            case 'frontend/pages/issue.tpl':
                self::addIssueTags($templateMgr, $request, $journal);
                break;
        }

        return false;
    }

    /**
     * @param \APP\core\Request $request
     * @param \APP\journal\Journal $journal
     */
    protected static function addJournalTags(TemplateManager $templateMgr, $request, $journal)
    {
        $title = $journal->getLocalizedName();
        $description = (string) ($templateMgr->getTemplateVars('journalDescription') ?: '');
        if ($description === '') {
            $description = (string) ($journal->getLocalizedData('searchDescription') ?: '');
        }
        $description = self::truncateDescription($description);
        $url = $request->getDispatcher()->url(
            $request,
            PKPApplication::ROUTE_PAGE,
            null,
            $journal->getPath(),
            urlLocaleForPage: ''
        );
        $image = self::getJournalImageUrl($request, $journal);
        $locale = LocaleConversion::toBcp47($journal->getPrimaryLocale());

        self::outputTags($templateMgr, [
            'title' => $title,
            'description' => $description,
            'url' => $url,
            'image' => $image,
            'type' => 'website',
            'siteName' => $title,
            'locale' => $locale,
        ]);
    }

    /**
     * @param \APP\core\Request $request
     * @param \APP\journal\Journal $journal
     */
    protected static function addIssueTags(TemplateManager $templateMgr, $request, $journal)
    {
        $issue = $templateMgr->getTemplateVars('issue');
        if (!$issue) {
            return;
        }

        $title = (string) ($templateMgr->getTemplateVars('issueIdentification') ?: $issue->getIssueIdentification());
        $description = '';
        if ($issue->hasDescription()) {
            $description = self::truncateDescription((string) $issue->getLocalizedDescription());
        }
        $url = $request->getDispatcher()->url(
            $request,
            PKPApplication::ROUTE_PAGE,
            null,
            'issue',
            'view',
            [$issue->getBestIssueId()],
            urlLocaleForPage: ''
        );
        $image = $issue->getLocalizedCoverImageUrl();
        if (!$image) {
            $image = self::getJournalImageUrl($request, $journal);
        }
        $locale = LocaleConversion::toBcp47($journal->getPrimaryLocale());

        self::outputTags($templateMgr, [
            'title' => $title,
            'description' => $description,
            'url' => $url,
            'image' => $image,
            'type' => 'website',
            'siteName' => $journal->getLocalizedName(),
            'locale' => $locale,
        ]);
    }

    /**
     * @param array $data Keys: title, description, url, image, type, siteName, locale
     */
    protected static function outputTags(TemplateManager $templateMgr, array $data)
    {
        self::addPropertyMeta($templateMgr, 'ogTitle', 'og:title', $data['title']);
        self::addPropertyMeta($templateMgr, 'ogDescription', 'og:description', $data['description']);
        self::addPropertyMeta($templateMgr, 'ogUrl', 'og:url', $data['url']);
        self::addPropertyMeta($templateMgr, 'ogType', 'og:type', $data['type']);
        self::addPropertyMeta($templateMgr, 'ogSiteName', 'og:site_name', $data['siteName']);
        self::addPropertyMeta($templateMgr, 'ogLocale', 'og:locale', $data['locale']);

        if (!empty($data['image'])) {
            self::addPropertyMeta($templateMgr, 'ogImage', 'og:image', $data['image']);
        }

        $twitterCard = !empty($data['image']) ? 'summary_large_image' : 'summary';
        self::addNameMeta($templateMgr, 'twitterCard', 'twitter:card', $twitterCard);
        self::addNameMeta($templateMgr, 'twitterTitle', 'twitter:title', $data['title']);
        self::addNameMeta($templateMgr, 'twitterDescription', 'twitter:description', $data['description']);
        if (!empty($data['image'])) {
            self::addNameMeta($templateMgr, 'twitterImage', 'twitter:image', $data['image']);
        }
    }

    protected static function addPropertyMeta(TemplateManager $templateMgr, string $id, string $property, string $content)
    {
        if ($content === '') {
            return;
        }

        $templateMgr->addHeader(
            $id,
            '<meta property="' . htmlspecialchars($property, ENT_QUOTES, 'UTF-8') . '" content="' . htmlspecialchars($content, ENT_QUOTES, 'UTF-8') . '"/>'
        );
    }

    protected static function addNameMeta(TemplateManager $templateMgr, string $id, string $name, string $content)
    {
        if ($content === '') {
            return;
        }

        $templateMgr->addHeader(
            $id,
            '<meta name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" content="' . htmlspecialchars($content, ENT_QUOTES, 'UTF-8') . '"/>'
        );
    }

    protected static function truncateDescription(string $text, int $maxLength = 300): string
    {
        $text = preg_replace('/\s+/u', ' ', strip_tags($text));
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength - 1) . '…';
    }

    /**
     * @param \APP\core\Request $request
     * @param \APP\journal\Journal $journal
     */
    protected static function getJournalImageUrl($request, $journal): string
    {
        $homepageImage = $journal->getLocalizedData('homepageImage');
        if ($homepageImage && !empty($homepageImage['uploadName'])) {
            return self::getContextFileUrl($request, $journal->getId(), $homepageImage['uploadName']);
        }

        $logo = $journal->getLocalizedData('pageHeaderLogoImage');
        if ($logo && !empty($logo['uploadName'])) {
            return self::getContextFileUrl($request, $journal->getId(), $logo['uploadName']);
        }

        return '';
    }

    /**
     * @param \APP\core\Request $request
     */
    protected static function getContextFileUrl($request, int $contextId, string $uploadName): string
    {
        $publicFileManager = new PublicFileManager();

        return $request->getBaseUrl() . '/' . $publicFileManager->getContextFilesPath($contextId) . '/' . $uploadName;
    }
}
