<?php
/**
 * @file Configuration.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Configuration
 * @brief Keeps the import options and common shared settings
 */

namespace APP\plugins\importexport\articleImporter;

use APP\core\Application;
use PKP\db\DAORegistry;
use PKP\security\Role;
use PKP\context\Context;
use PKP\user\User;
use PKP\submission\Genre;
use APP\facades\Repo;
use PKP\userGroup\relationships\UserGroupStage;

class Configuration
{
    /** @var string Default title for sections */
    private string $_defaultSectionName;
    /** @var string[] List of classes that can parse XML articles */
    private array $_parsers;
    /** @var \Context Context */
    private Context $_context;
    /** @var User User instance */
    private User $_user;
    /** @var User Editor instance */
    private User $_editor;
    /** @var string Default email */
    private string $_email;
    /** @var string Import path */
    private string $_importPath;
    /** @var int Editor's user group ID */
    private int $_editorGroupId;
    /** @var int|null Author's user group ID */
    private ?int $_authorGroupId;
    /** @var Genre Submission genre instance */
    private Genre $_genre;
    /** @var string[] File extensions recognized as images */
    private array $_imageExt;
    /** @var string base filename for issue covers */
    private string $_issueCoverFilename;

    /**
     * Constructor
     *
     * @param string[] $parsers List of parser classes
     * @param string $contextPath Path of the context
     * @param string $username User to whom imported articles will be assigned
     * @param string $editorUsername Editor to whom imported articles should be assigned
     * @param string $email Default email when the author email is not provided in the XML
     * @param string $importPath Base path where the "volume/issue/article" structure is kept
     */
    public function __construct(array $parsers, string $contextPath, string $username, string $editorUsername, string $email, string $importPath, string $defaultSectionName = 'Articles')
    {
        $this->_defaultSectionName = $defaultSectionName;
        $this->_parsers = $parsers;

        if (!$this->_context = Application::getContextDAO()->getByPath($contextPath)) {
            throw new \InvalidArgumentException(__('plugins.importexport.articleImporter.unknownJournal', ['journal' => $contextPath]));
        }

        [$this->_user, $this->_editor] = array_map(function ($username) {
            if (!$entity = Repo::user()->getByUsername($username)) {
                throw new \InvalidArgumentException(__('plugins.importexport.articleImporter.unknownUser', ['username' => $username]));
            }
            return $entity;
        }, [$username, $editorUsername]);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(__('plugins.importexport.articleImporter.unknownEmail', ['email' => $email]));
        }
        $this->_email = $email;

        if (!is_dir($importPath)) {
            throw new \InvalidArgumentException(__('plugins.importexport.articleImporter.directoryDoesNotExist', ['directory' => $importPath]));
        }
        $this->_importPath = $importPath;

        // Finds the user group ID for the editor
        $editorUserGroups = Repo::userGroup()->getByRoleIds([Role::ROLE_ID_MANAGER], $this->_context->getId());
        foreach ($editorUserGroups as $userGroup) {
            if (UserGroupStage::withUserGroupId($userGroup->id)->withStageId(\WORKFLOW_STAGE_ID_PRODUCTION)->count()) {
                $this->_editorGroupId = $userGroup->id;
                break;
            }
        }
        if (!$this->_editorGroupId) {
            throw new \Exception(__('plugins.importexport.articleImporter.missingEditorGroupId'));
        }

        // Finds the user group ID for the authors
        $authorUserGroups = Repo::userGroup()->getByRoleIds([Role::ROLE_ID_AUTHOR], $this->_context->getId());
        $this->_authorGroupId = $authorUserGroups->first()->id;

        // Retrieves the genre for submissions
        $this->_genre = DAORegistry::getDAO('GenreDAO')->getByKey('SUBMISSION', $this->_context->getId());

        $this->_imageExt = ['tif', 'tiff', 'png', 'jpg', 'jpeg'];
        $this->_issueCoverFilename = 'cover';
    }

    /**
     * Retrieves the context instance
     */
    public function getContext(): Context
    {
        return $this->_context;
    }

    /**
     * Retrieves the user instance
    */
    public function getUser(): User
    {
        return $this->_user;
    }

    /**
     * Retrieves the user instance
     */
    public function getEditor(): User
    {
        return $this->_editor;
    }

    /**
     * Retrieves the default email which will be assigned to authors (when absent)
     *
     * @return \Context
     */
    public function getEmail(): string
    {
        return $this->_email;
    }

    /**
     * Retrieves the import base path
     */
    public function getImportPath(): string
    {
        return $this->_importPath;
    }

    /**
     * Retrieves the editor user group ID
     */
    public function getEditorGroupId(): int
    {
        return $this->_editorGroupId;
    }

    /**
     * Retrieves the author user group ID
     *
     * @return ?int
     */
    public function getAuthorGroupId(): ?int
    {
        return $this->_authorGroupId;
    }

    /**
     * Retrieves the submission genre
     */
    public function getSubmissionGenre(): Genre
    {
        return $this->_genre;
    }

    /**
     * Retrieves an article iterator
     */
    public function getArticleIterator(): ArticleIterator
    {
        return new ArticleIterator($this->getImportPath());
    }

    /**
     * Retrieves the list of parsers
     *
     * @return string[]
     */
    public function getParsers(): array
    {
        return $this->_parsers;
    }

    /**
     * Retrieves the default section name
     */
    public function getDefaultSectionName(): string
    {
        return $this->_defaultSectionName;
    }

    /**
     * Retrieves the array of known image extensions
     *
     * @return string[]
     */
    public function getImageExtensions(): array
    {
        return $this->_imageExt;
    }

    /**
     * Retrieves the base name for an issue cover file
     */
    public function getIssueCoverFilename(): string
    {
        return $this->_issueCoverFilename;
    }
}
