<?php

declare(strict_types=1);

namespace Symplify\GitWrapper;

use Nette\Utils\Strings;
use Symplify\GitWrapper\Exception\GitException;
use Symplify\GitWrapper\ValueObject\CommandName;

/**
 * All commands executed via an instance of this class act on the working copy  that is set through the constructor.
 *
 * @see \Symplify\GitWrapper\Tests\GitWorkingCopyTest
 */
final class GitWorkingCopy
{
    /**
     * @var string
     */
    private const _T = '-t';

    /**
     * @var string
     */
    private const _M = '-m';

    /**
     * A boolean flagging whether the repository is cloned.
     *
     * If the variable is null, the a rudimentary check will be performed to see if the directory looks like it is a
     * working copy.
     */
    private ?bool $cloned = null;

    public function __construct(
        /**
         * The GitWrapper object that likely instantiated this class.
         */
        private GitWrapper $gitWrapper,
        /**
         * Path to the directory containing the working copy.
         */
        private string $directory
    ) {
    }

    public function getWrapper(): GitWrapper
    {
        return $this->gitWrapper;
    }

    public function getDirectory(): string
    {
        return $this->directory;
    }

    public function setCloned(bool $cloned): void
    {
        $this->cloned = $cloned;
    }

    /**
     * Checks whether a repository has already been cloned to this directory.
     *
     * If the flag is not set, test if it looks like we're at a git directory.
     */
    public function isCloned(): bool
    {
        if ($this->cloned === null) {
            $gitDir = $this->directory;
            if (is_dir($gitDir . '/.git')) {
                $gitDir .= '/.git';
            }

            $this->cloned = is_dir($gitDir . '/objects') && is_dir($gitDir . '/refs') && is_file($gitDir . '/HEAD');
        }

        return $this->cloned;
    }

    /**
     * Runs a Git command and returns the output.
     *
     * @param mixed[] $argsAndOptions
     */
    public function run(string $command, array $argsAndOptions = [], bool $setDirectory = true): string
    {
        $gitCommand = new GitCommand($command, ...$argsAndOptions);
        if ($setDirectory) {
            $gitCommand->setDirectory($this->directory);
        }

        return $this->gitWrapper->run($gitCommand);
    }

    /**
     * Returns the output of a `git status -s` command.
     */
    public function getStatus(): string
    {
        return $this->run(CommandName::STATUS, ['-s']);
    }

    /**
     * Returns true if there are changes to commit.
     */
    public function hasChanges(): bool
    {
        $status = $this->getStatus();
        return $status !== '';
    }

    /**
     * Returns whether HEAD has a remote tracking branch.
     */
    public function isTracking(): bool
    {
        try {
            $this->run(CommandName::REV_PARSE, ['@{u}']);
        } catch (GitException) {
            return false;
        }

        return true;
    }

    /**
     * Returns whether HEAD is up-to-date with its remote tracking branch.
     */
    public function isUpToDate(): bool
    {
        if (! $this->isTracking()) {
            throw new GitException(
                'Error: HEAD does not have a remote tracking branch. Cannot check if it is up-to-date.'
            );
        }

        $mergeBase = $this->run(CommandName::MERGE_BASE, ['@', '@{u}']);
        $remoteSha = $this->run(CommandName::REV_PARSE, ['@{u}']);
        return $mergeBase === $remoteSha;
    }

    /**
     * Returns whether HEAD is ahead of its remote tracking branch.
     *
     * If this returns true it means that commits are present locally which have not yet been pushed to the remote.
     */
    public function isAhead(): bool
    {
        if (! $this->isTracking()) {
            throw new GitException('Error: HEAD does not have a remote tracking branch. Cannot check if it is ahead.');
        }

        $mergeBase = $this->run(CommandName::MERGE_BASE, ['@', '@{u}']);
        $localSha = $this->run(CommandName::REV_PARSE, ['@']);
        $remoteSha = $this->run(CommandName::REV_PARSE, ['@{u}']);

        if ($mergeBase !== $remoteSha) {
            return false;
        }

        return $localSha !== $remoteSha;
    }

    /**
     * Returns whether HEAD is behind its remote tracking branch.
     *
     * If this returns true it means that a pull is needed to bring the branch up-to-date with the remote.
     */
    public function isBehind(): bool
    {
        if (! $this->isTracking()) {
            throw new GitException('Error: HEAD does not have a remote tracking branch. Cannot check if it is behind.');
        }

        $mergeBase = $this->run(CommandName::MERGE_BASE, ['@', '@{u}']);
        $localSha = $this->run(CommandName::REV_PARSE, ['@']);
        $remoteSha = $this->run(CommandName::REV_PARSE, ['@{u}']);

        if ($mergeBase !== $localSha) {
            return false;
        }

        return $localSha !== $remoteSha;
    }

    /**
     * Returns whether HEAD needs to be merged with its remote tracking branch.
     *
     * If this returns true it means that HEAD has diverged from its remote tracking branch; new commits are present
     * locally as well as on the remote.
     */
    public function needsMerge(): bool
    {
        if (! $this->isTracking()) {
            throw new GitException('Error: HEAD does not have a remote tracking branch. Cannot check if it is behind.');
        }

        $mergeBase = $this->run(CommandName::MERGE_BASE, ['@', '@{u}']);
        $localSha = $this->run(CommandName::REV_PARSE, ['@']);
        $remoteSha = $this->run(CommandName::REV_PARSE, ['@{u}']);

        if ($mergeBase === $localSha) {
            return false;
        }

        return $mergeBase !== $remoteSha;
    }

    /**
     * Returns a GitBranches object containing information on the repository's branches.
     */
    public function getBranches(): GitBranches
    {
        return new GitBranches($this);
    }

    /**
     * This is synonymous with `git push origin tag v1.2.3`.
     *
     * @param string $repository The destination of the push operation, which is either a URL or name of
     * the remote. Defaults to "origin".
     * @param mixed[] $options
     */
    public function pushTag(string $tag, string $repository = 'origin', array $options = []): string
    {
        return $this->push($repository, 'tag', $tag, $options);
    }

    /**
     * This is synonymous with `git push --tags origin`.
     *
     * @param string $repository The destination of the push operation, which is either a URL or name of the remote.
     * @param mixed[] $options
     */
    public function pushTags(string $repository = 'origin', array $options = []): string
    {
        $options['tags'] = true;
        return $this->push($repository, $options);
    }

    /**
     * Fetches all remotes.
     *
     * This is synonymous with `git fetch --all`.
     *
     * @param mixed[] $options
     */
    public function fetchAll(array $options = []): string
    {
        $options['all'] = true;
        return $this->fetch($options);
    }

    /**
     * Create a new branch and check it out.
     *
     * This is synonymous with `git checkout -b`.
     *
     * @param mixed[] $options
     */
    public function checkoutNewBranch(string $branch, array $options = []): string
    {
        $options['b'] = true;
        return $this->checkout($branch, $options);
    }

    /**
     * Adds a remote to the repository.
     *
     * @param mixed[] $options An associative array of options, with the following keys:
     * - -f: Boolean, set to true to run git fetch immediately after the
     * remote is set up. Defaults to false.
     * - --tags: Boolean. By default only the tags from the fetched branches
     * are imported when git fetch is run. Set this to true to import every
     * tag from the remote repository. Defaults to false.
     * - --no-tags: Boolean, when set to true, git fetch does not import tags
     * from the remote repository. Defaults to false.
     * - -t: Optional array of branch names to track. If left empty, all
     * branches will be tracked.
     * - -m: Optional name of the master branch to track. This will set up a
     * symbolic ref 'refs/remotes/<name>/HEAD which points at the specified
     * master branch on the remote. When omitted, no symbolic ref will be
     * created.
     */
    public function addRemote(string $name, string $url, array $options = []): string
    {
        $this->ensureAddRemoteArgsAreValid($name, $url);

        $args = ['add'];

        // Add boolean options.
        foreach (['-f', '--tags', '--no-tags'] as $option) {
            if (isset($options[$option])) {
                $args[] = $option;
            }
        }

        // Add tracking branches.
        if (isset($options[self::_T]) && is_array($options[self::_T])) {
            foreach ($options[self::_T] as $branch) {
                $args[] = self::_T;
                $args[] = $branch;
            }
        }

        // Add master branch.
        if (isset($options[self::_M])) {
            $args[] = self::_M;
            $args[] = $options[self::_M];
        }

        // Add remote name and URL.
        $args[] = $name;
        $args[] = $url;

        return $this->remote(...$args);
    }

    public function removeRemote(string $name): string
    {
        return $this->remote('rm', $name);
    }

    public function hasRemote(string $name): bool
    {
        return array_key_exists($name, $this->getRemotes());
    }

    /**
     * @return string[] An associative array with the following keys:
     *  - fetch: the fetch URL.
     *  - push: the push URL.
     */
    public function getRemote(string $name): array
    {
        if (! $this->hasRemote($name)) {
            throw new GitException(sprintf('The remote "%s" does not exist.', $name));
        }

        return $this->getRemotes()[$name];
    }

    /**
     * @return string[][] An associative array, keyed by remote name, containing an associative array with keys
     *  - fetch: the fetch URL.
     *  - push: the push URL.
     */
    public function getRemotes(): array
    {
        $result = rtrim($this->remote());
        if ($result === '') {
            return [];
        }

        $remotes = [];

        $resultLines = $this->splitByNewline($result);
        foreach ($resultLines as $remote) {
            $remotes[$remote][CommandName::FETCH] = $this->getRemoteUrl($remote);
            $remotes[$remote][CommandName::PUSH] = $this->getRemoteUrl($remote, CommandName::PUSH);
        }

        return $remotes;
    }

    /**
     * Returns the fetch or push URL of a given remote.
     *
     * @param string $operation The operation for which to return the remote. Can be either 'fetch' or 'push'.
     */
    public function getRemoteUrl(string $remote, string $operation = CommandName::FETCH): string
    {
        $argsAndOptions = ['get-url', $remote];

        if ($operation === CommandName::PUSH) {
            $argsAndOptions[] = '--push';
        }

        return rtrim($this->remote(...$argsAndOptions));
    }

    /**
     * @code $git->add('some/file.txt');
     *
     * @param mixed[] $options
     */
    public function add(string $filepattern, array $options = []): string
    {
        return $this->run(CommandName::ADD, [$filepattern, $options]);
    }

    /**
     * @code $git->apply('the/file/to/read/the/patch/from');
     *
     * @param mixed ...$argsAndOptions
     */
    public function apply(...$argsAndOptions): string
    {
        return $this->run(CommandName::APPLY, $argsAndOptions);
    }

    /**
     * Find by binary search the change that introduced a bug.
     *
     * @code $git->bisect('good', '2.6.13-rc2');
     * $git->bisect('view', ['stat' => true]);
     *
     * @param mixed ...$argsAndOptions
     */
    public function bisect(...$argsAndOptions): string
    {
        return $this->run(CommandName::BISECT, $argsAndOptions);
    }

    /**
     * @code $git->branch('my2.6.14', 'v2.6.14');
     * $git->branch('origin/html', 'origin/man', ['d' => true, 'r' => 'origin/todo']);
     *
     * @param mixed ...$argsAndOptions
     */
    public function branch(...$argsAndOptions): string
    {
        return $this->run(CommandName::BRANCH, $argsAndOptions);
    }

    /**
     * @code $git->checkout('new-branch', ['b' => true]);
     *
     * @param mixed ...$argsAndOptions
     */
    public function checkout(...$argsAndOptions): string
    {
        return $this->run(CommandName::CHECKOUT, $argsAndOptions);
    }

    /**
     * Executes a `git clone` command.
     *
     * @code $git->cloneRepository('git://github.com/symplify/git-wrapper.git');
     *
     * @param mixed[] $options
     */
    public function cloneRepository(string $repository, array $options = []): string
    {
        $argsAndOptions = [$repository, $this->directory, $options];
        return $this->run(CommandName::CLONE, $argsAndOptions, false);
    }

    /**
     * Record changes to the repository. If only one argument is passed, it is assumed to be the commit message.
     * Therefore `$git->commit('Message');` yields a `git commit -am "Message"` command.
     *
     * @code $git->commit('My commit message');
     * $git->commit('Makefile', ['m' => 'My commit message']);
     *
     * @param mixed ...$argsAndOptions
     */
    public function commit(...$argsAndOptions): string
    {
        if (isset($argsAndOptions[0]) && is_string($argsAndOptions[0]) && ! isset($argsAndOptions[1])) {
            $argsAndOptions[0] = [
                'm' => $argsAndOptions[0],
                'a' => true,
            ];
        }

        return $this->run(CommandName::COMMIT, $argsAndOptions);
    }

    /**
     * @code $git->config('user.email', 'testing@email.com');
     * $git->config('user.name', 'Chris Pliakas');
     *
     * @param mixed ...$argsAndOptions
     */
    public function config(...$argsAndOptions): string
    {
        return $this->run(CommandName::CONFIG, $argsAndOptions);
    }

    /**
     * @code $git->diff();
     * $git->diff('topic', 'master');
     *
     * @param mixed ...$argsAndOptions
     */
    public function diff(...$argsAndOptions): string
    {
        return $this->run(CommandName::DIFF, $argsAndOptions);
    }

    /**
     * @code $git->fetch('origin');
     * $git->fetch(['all' => true]);
     *
     * @api
     * @param mixed ...$argsAndOptions
     */
    public function fetch(...$argsAndOptions): string
    {
        return $this->run(CommandName::FETCH, $argsAndOptions);
    }

    /**
     * Print lines matching a pattern.
     *
     * @code $git->grep('time_t', '--', '*.[ch]');
     *
     * @param mixed ...$argsAndOptions
     */
    public function grep(...$argsAndOptions): string
    {
        return $this->run(CommandName::GREP, $argsAndOptions);
    }

    /**
     * Create an empty git repository or reinitialize an existing one.
     *
     * @code $git->init(['bare' => true]);
     *
     * @param mixed[] $options
     */
    public function init(array $options = []): string
    {
        $argsAndOptions = [$this->directory, $options];
        return $this->run(CommandName::INIT, $argsAndOptions, false);
    }

    /**
     * @code $git->log(['no-merges' => true]);
     * $git->log('v2.6.12..', 'include/scsi', 'drivers/scsi');
     *
     * @param mixed ...$argsAndOptions
     */
    public function log(...$argsAndOptions): string
    {
        return $this->run(CommandName::LOG, $argsAndOptions);
    }

    /**
     * @code $git->merge('fixes', 'enhancements');
     *
     * @param mixed ...$argsAndOptions
     */
    public function merge(...$argsAndOptions): string
    {
        return $this->run(CommandName::MERGE, $argsAndOptions);
    }

    /**
     * @code $git->mv('orig.txt', 'dest.txt');
     *
     * @param mixed[] $options
     */
    public function mv(string $source, string $destination, array $options = []): string
    {
        $argsAndOptions = [$source, $destination, $options];
        return $this->run(CommandName::MV, $argsAndOptions);
    }

    /**
     * @code $git->pull('upstream', 'master');
     *
     * @param mixed ...$argsAndOptions
     */
    public function pull(...$argsAndOptions): string
    {
        return $this->run(CommandName::PULL, $argsAndOptions);
    }

    /**
     * @code $git->push('upstream', 'master');
     *
     * @param mixed ...$argsAndOptions
     */
    public function push(...$argsAndOptions): string
    {
        return $this->run(CommandName::PUSH, $argsAndOptions);
    }

    /**
     * @code $git->rebase('subsystem@{1}', ['onto' => 'subsystem']);
     *
     * @param mixed ...$argsAndOptions
     */
    public function rebase(...$argsAndOptions): string
    {
        return $this->run(CommandName::REBASE, $argsAndOptions);
    }

    /**
     * @code $git->remote('add', 'upstream', 'git://github.com/symplify/git-wrapper.git');
     *
     * @param mixed ...$argsAndOptions
     */
    public function remote(...$argsAndOptions): string
    {
        return $this->run(CommandName::REMOTE, $argsAndOptions);
    }

    /**
     * @code $git->reset(['hard' => true]);
     *
     * @param mixed ...$argsAndOptions
     */
    public function reset(...$argsAndOptions): string
    {
        return $this->run(CommandName::RESET, $argsAndOptions);
    }

    /**
     * @code $git->rm('oldfile.txt');
     *
     * @param mixed[] $options
     */
    public function rm(string $filepattern, array $options = []): string
    {
        $args = [$filepattern, $options];
        return $this->run(CommandName::RM, $args);
    }

    /**
     * @code $git->show('v1.0.0');
     *
     * @param mixed[] $options
     */
    public function show(string $object, array $options = []): string
    {
        $args = [$object, $options];
        return $this->run(CommandName::SHOW, $args);
    }

    /**
     * @code $git->status(['s' => true]);
     *
     * @param mixed ...$argsAndOptions
     */
    public function status(...$argsAndOptions): string
    {
        return $this->run(CommandName::STATUS, $argsAndOptions);
    }

    /**
     * @code $git->tag('v1.0.0');
     *
     * @param mixed ...$argsAndOptions
     */
    public function tag(...$argsAndOptions): string
    {
        return $this->run(CommandName::TAG, $argsAndOptions);
    }

    /**
     * @code $git->clean('-d', '-f');
     *
     * @param string ...$argsAndOptions
     */
    public function clean(...$argsAndOptions): string
    {
        return $this->run(CommandName::CLEAN, $argsAndOptions);
    }

    /**
     * @code $git->archive('HEAD', ['o' => '/path/to/archive']);
     *
     * @param mixed ...$argsAndOptions
     */
    public function archive(...$argsAndOptions): string
    {
        return $this->run(CommandName::ARCHIVE, $argsAndOptions);
    }

    /**
     * @api
     * Returns a GitTags object containing information on the repository's tags.
     */
    public function tags(): GitTags
    {
        return new GitTags($this);
    }

    /**
     * @api
     * Returns a GitCommits object that contains information about the commits of the current branch.
     */
    public function commits(): GitCommits
    {
        return new GitCommits($this);
    }

    private function ensureAddRemoteArgsAreValid(string $name, string $url): void
    {
        if ($name === '') {
            throw new GitException('Cannot add remote without a name.');
        }

        if ($url === '') {
            throw new GitException('Cannot add remote without a URL.');
        }
    }

    /**
     * @return string[]
     */
    private function splitByNewline(string $string): array
    {
        return Strings::split($string, '#\R#');
    }
}
