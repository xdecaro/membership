<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Service;

defined('_JEXEC') or die;

use InvalidArgumentException;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use RuntimeException;
use Throwable;

final class CompetitionCardNumberService
{
    private const MAX_SEQUENCE = 9999999;

    public function __construct(
        private DatabaseInterface $db,
        private OrganizationsIntegrationService $organizations
    ) {}

    public static function formatNumber(string $issuerCode, string $tournamentCode, int $seasonYear, int $sequence, int $padding = 7): string
    {
        $issuerCode = self::normalizeCode($issuerCode);
        $tournamentCode = self::normalizeCode($tournamentCode);

        if ($issuerCode === '') {
            throw new InvalidArgumentException('Issuer code is required.');
        }
        if ($tournamentCode === '') {
            throw new InvalidArgumentException('Tournament code is required.');
        }
        if ($seasonYear < 1000 || $seasonYear > 9999) {
            throw new InvalidArgumentException('Season year must use four digits.');
        }
        if ($sequence < 1 || $sequence > self::MAX_SEQUENCE) {
            throw new InvalidArgumentException('Sequence is outside the supported range.');
        }
        $padding = max(1, min(12, $padding));

        $number = sprintf('%s-%s-%04d-%0' . $padding . 'd', $issuerCode, $tournamentCode, $seasonYear, $sequence);
        if (strlen($number) > 100) {
            throw new InvalidArgumentException('Card number format exceeds 100 characters.');
        }

        return $number;
    }

    public function allocate(string $issuerOrganizationUuid, array $season, string $nowSql, int $padding = 7): string
    {
        $issuerOrganizationUuid = strtolower(trim($issuerOrganizationUuid));
        if ($issuerOrganizationUuid === '') {
            throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_CARD_NUMBER_ISSUER_REQUIRED'));
        }

        $organization = $this->organizations->getOrganization($issuerOrganizationUuid);
        if (!is_array($organization)) {
            throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_CARD_NUMBER_ISSUER_REQUIRED'));
        }

        $issuerCode = trim((string) ($organization['short_name'] ?? $organization['code'] ?? ''));
        if ($issuerCode === '') {
            throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_CARD_NUMBER_ISSUER_CODE'));
        }

        $tournamentCode = trim((string) ($season['tournament_code'] ?? ''));
        if ($tournamentCode === '') {
            throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_CARD_NUMBER_TOURNAMENT_CODE'));
        }

        $seasonYear = (int) ($season['season_year'] ?? 0);
        if ($seasonYear < 1000 || $seasonYear > 9999) {
            throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_CARD_NUMBER_SEASON_YEAR'));
        }

        $prefix = sprintf(
            '%s-%s-%04d',
            self::normalizeRequiredCode($issuerCode, 'COM_DECAROMEMBERSHIP_ERROR_CARD_NUMBER_ISSUER_CODE'),
            self::normalizeRequiredCode($tournamentCode, 'COM_DECAROMEMBERSHIP_ERROR_CARD_NUMBER_TOURNAMENT_CODE'),
            $seasonYear
        );
        $padding = max(1, min(12, $padding));
        if (strlen($prefix) + 1 + $padding > 100) {
            throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_CARD_NUMBER_FORMAT_TOO_LONG'));
        }
        $seriesKey = hash('sha256', $prefix);
        $existingMax = $this->findExistingMax($prefix, $padding);
        $maxSequence = min(self::MAX_SEQUENCE, (10 ** $padding) - 1);
        $sequence = $this->nextSequence($seriesKey, $prefix, $existingMax, $nowSql, $maxSequence);

        try {
            return self::formatNumber($issuerCode, $tournamentCode, $seasonYear, $sequence, $padding);
        } catch (InvalidArgumentException $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }
    }

    public function allocateAssociation(string $issuerOrganizationUuid, int $year, string $nowSql, int $padding = 7): string
    {
        $issuerOrganizationUuid = strtolower(trim($issuerOrganizationUuid));
        if ($issuerOrganizationUuid === '') {
            throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_CARD_NUMBER_ISSUER_REQUIRED'));
        }

        $organization = $this->organizations->getOrganization($issuerOrganizationUuid);
        if (!is_array($organization)) {
            throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_CARD_NUMBER_ISSUER_REQUIRED'));
        }

        $issuerCode = trim((string) ($organization['short_name'] ?? $organization['code'] ?? ''));
        $issuerCode = self::normalizeRequiredCode($issuerCode, 'COM_DECAROMEMBERSHIP_ERROR_CARD_NUMBER_ISSUER_CODE');
        if ($year < 1000 || $year > 9999) {
            throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_CARD_NUMBER_SEASON_YEAR'));
        }

        $padding = max(1, min(12, $padding));
        $prefix = sprintf('%s-%04d', $issuerCode, $year);
        if (strlen($prefix) + 1 + $padding > 100) {
            throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_CARD_NUMBER_FORMAT_TOO_LONG'));
        }

        $seriesKey = hash('sha256', $prefix);
        $existingMax = $this->findExistingMax($prefix, $padding);
        $maxSequence = min(self::MAX_SEQUENCE, (10 ** $padding) - 1);
        $sequence = $this->nextSequence($seriesKey, $prefix, $existingMax, $nowSql, $maxSequence);

        return sprintf('%s-%0' . $padding . 'd', $prefix, $sequence);
    }

    private static function normalizeRequiredCode(string $value, string $errorKey): string
    {
        $value = self::normalizeCode($value);
        if ($value === '') {
            throw new RuntimeException(Text::_($errorKey));
        }

        return $value;
    }

    private static function normalizeCode(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (function_exists('iconv')) {
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($ascii) && $ascii !== '') {
                $value = $ascii;
            }
        }

        $value = strtoupper($value);
        $value = preg_replace('/[^A-Z0-9]+/', '-', $value) ?? '';
        $value = preg_replace('/-+/', '-', $value) ?? '';

        return trim($value, '-');
    }

    private function findExistingMax(string $prefix, int $padding = 7): int
    {
        $like = $prefix . '-%';
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('card_number'))
            ->from($this->db->quoteName('#__decaromembership_cards'))
            ->where($this->db->quoteName('card_number') . ' LIKE :prefix')
            ->bind(':prefix', $like);

        $max = 0;
        $padding = max(1, min(12, $padding));
        $pattern = '/^' . preg_quote($prefix, '/') . '-(\d{' . $padding . '})$/';
        foreach ((array) $this->db->setQuery($query)->loadColumn() as $number) {
            if (preg_match($pattern, (string) $number, $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return $max;
    }

    private function nextSequence(string $seriesKey, string $prefix, int $existingMax, string $nowSql, int $maxSequence = self::MAX_SEQUENCE): int
    {
        $started = false;
        try {
            $this->db->transactionStart();
            $started = true;

            // Atomic upsert: creates the series on first use and synchronizes a
            // restored/legacy series with the highest existing formatted number.
            $table = $this->db->quoteName('#__decaromembership_card_sequences');
            $upsert = 'INSERT INTO ' . $table . ' ('
                . implode(', ', [
                    $this->db->quoteName('series_key'),
                    $this->db->quoteName('prefix'),
                    $this->db->quoteName('last_number'),
                    $this->db->quoteName('created'),
                    $this->db->quoteName('modified'),
                ])
                . ') VALUES ('
                . $this->db->quote($seriesKey) . ', '
                . $this->db->quote($prefix) . ', '
                . (int) $existingMax . ', '
                . $this->db->quote($nowSql) . ', '
                . $this->db->quote($nowSql)
                . ') ON DUPLICATE KEY UPDATE '
                . $this->db->quoteName('prefix') . ' = VALUES(' . $this->db->quoteName('prefix') . '), '
                . $this->db->quoteName('last_number') . ' = GREATEST('
                . $this->db->quoteName('last_number') . ', VALUES(' . $this->db->quoteName('last_number') . ')), '
                . $this->db->quoteName('modified') . ' = VALUES(' . $this->db->quoteName('modified') . ')';
            $this->db->setQuery($upsert)->execute();

            $lockedSql = 'SELECT ' . $this->db->quoteName('last_number')
                . ' FROM ' . $table
                . ' WHERE ' . $this->db->quoteName('series_key') . ' = ' . $this->db->quote($seriesKey)
                . ' FOR UPDATE';
            $stored = (int) $this->db->setQuery($lockedSql)->loadResult();
            $next = $stored + 1;

            if ($next > $maxSequence) {
                throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_CARD_NUMBER_SEQUENCE_LIMIT'));
            }

            $update = $this->db->getQuery(true)
                ->update($table)
                ->set($this->db->quoteName('last_number') . ' = :last_number')
                ->set($this->db->quoteName('modified') . ' = :modified')
                ->where($this->db->quoteName('series_key') . ' = :series_key')
                ->bind(':last_number', $next, ParameterType::INTEGER)
                ->bind(':modified', $nowSql)
                ->bind(':series_key', $seriesKey);
            $this->db->setQuery($update)->execute();

            $this->db->transactionCommit();
            $started = false;

            return $next;
        } catch (Throwable $e) {
            if ($started) {
                try {
                    $this->db->transactionRollback();
                } catch (Throwable) {
                }
            }
            throw $e;
        }
    }

}
