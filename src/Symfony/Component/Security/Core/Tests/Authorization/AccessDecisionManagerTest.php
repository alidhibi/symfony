<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authorization;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Exception\LogicException;
use Symfony\Component\Security\Core\Tests\Authorization\Stub\VoterWithoutInterface;

class AccessDecisionManagerTest extends TestCase
{
    public function testSetUnsupportedStrategy(): void
    {
        $this->expectException('InvalidArgumentException');
        new AccessDecisionManager([$this->getVoter(VoterInterface::ACCESS_GRANTED)], 'fooBar');
    }

    /**
     * @dataProvider getStrategyTests
     */
    public function testStrategies(string $strategy, $voters, bool $allowIfAllAbstainDecisions, bool $allowIfEqualGrantedDeniedDecisions, bool $expected): void
    {
        $token = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class)->getMock();
        $manager = new AccessDecisionManager($voters, $strategy, $allowIfAllAbstainDecisions, $allowIfEqualGrantedDeniedDecisions);

        $this->assertSame($expected, $manager->decide($token, ['ROLE_FOO']));
    }

    /**
     * @dataProvider getStrategiesWith2RolesTests
     */
    public function testStrategiesWith2Roles(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface $token, string $strategy, $voter, bool $expected): void
    {
        $manager = new AccessDecisionManager([$voter], $strategy);

        $this->assertSame($expected, $manager->decide($token, ['ROLE_FOO', 'ROLE_BAR']));
    }

    public function getStrategiesWith2RolesTests(): array
    {
        $token = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class)->getMock();

        return [
            [$token, 'affirmative', $this->getVoter(VoterInterface::ACCESS_DENIED), false],
            [$token, 'affirmative', $this->getVoter(VoterInterface::ACCESS_GRANTED), true],

            [$token, 'consensus', $this->getVoter(VoterInterface::ACCESS_DENIED), false],
            [$token, 'consensus', $this->getVoter(VoterInterface::ACCESS_GRANTED), true],

            [$token, 'unanimous', $this->getVoterFor2Roles($token, VoterInterface::ACCESS_DENIED, VoterInterface::ACCESS_DENIED), false],
            [$token, 'unanimous', $this->getVoterFor2Roles($token, VoterInterface::ACCESS_DENIED, VoterInterface::ACCESS_GRANTED), false],
            [$token, 'unanimous', $this->getVoterFor2Roles($token, VoterInterface::ACCESS_GRANTED, VoterInterface::ACCESS_DENIED), false],
            [$token, 'unanimous', $this->getVoterFor2Roles($token, VoterInterface::ACCESS_GRANTED, VoterInterface::ACCESS_GRANTED), true],
        ];
    }

    protected function getVoterFor2Roles($token, $vote1, $vote2)
    {
        $voter = $this->getMockBuilder(\Symfony\Component\Security\Core\Authorization\Voter\VoterInterface::class)->getMock();
        $voter->expects($this->any())
              ->method('vote')
              ->willReturnMap([
                  [$token, null, ['ROLE_FOO'], $vote1],
                  [$token, null, ['ROLE_BAR'], $vote2],
              ])
        ;

        return $voter;
    }

    public function getStrategyTests(): array
    {
        return [
            // affirmative
            [AccessDecisionManager::STRATEGY_AFFIRMATIVE, $this->getVoters(1, 0, 0), false, true, true],
            [AccessDecisionManager::STRATEGY_AFFIRMATIVE, $this->getVoters(1, 2, 0), false, true, true],
            [AccessDecisionManager::STRATEGY_AFFIRMATIVE, $this->getVoters(0, 1, 0), false, true, false],
            [AccessDecisionManager::STRATEGY_AFFIRMATIVE, $this->getVoters(0, 0, 1), false, true, false],
            [AccessDecisionManager::STRATEGY_AFFIRMATIVE, $this->getVoters(0, 0, 1), true, true, true],

            // consensus
            [AccessDecisionManager::STRATEGY_CONSENSUS, $this->getVoters(1, 0, 0), false, true, true],
            [AccessDecisionManager::STRATEGY_CONSENSUS, $this->getVoters(1, 2, 0), false, true, false],
            [AccessDecisionManager::STRATEGY_CONSENSUS, $this->getVoters(2, 1, 0), false, true, true],

            [AccessDecisionManager::STRATEGY_CONSENSUS, $this->getVoters(0, 0, 1), false, true, false],

            [AccessDecisionManager::STRATEGY_CONSENSUS, $this->getVoters(0, 0, 1), true, true, true],

            [AccessDecisionManager::STRATEGY_CONSENSUS, $this->getVoters(2, 2, 0), false, true, true],
            [AccessDecisionManager::STRATEGY_CONSENSUS, $this->getVoters(2, 2, 1), false, true, true],

            [AccessDecisionManager::STRATEGY_CONSENSUS, $this->getVoters(2, 2, 0), false, false, false],
            [AccessDecisionManager::STRATEGY_CONSENSUS, $this->getVoters(2, 2, 1), false, false, false],

            // unanimous
            [AccessDecisionManager::STRATEGY_UNANIMOUS, $this->getVoters(1, 0, 0), false, true, true],
            [AccessDecisionManager::STRATEGY_UNANIMOUS, $this->getVoters(1, 0, 1), false, true, true],
            [AccessDecisionManager::STRATEGY_UNANIMOUS, $this->getVoters(1, 1, 0), false, true, false],

            [AccessDecisionManager::STRATEGY_UNANIMOUS, $this->getVoters(0, 0, 2), false, true, false],
            [AccessDecisionManager::STRATEGY_UNANIMOUS, $this->getVoters(0, 0, 2), true, true, true],
        ];
    }

    /**
     * @return mixed[]
     */
    protected function getVoters($grants, $denies, $abstains): array
    {
        $voters = [];
        for ($i = 0; $i < $grants; ++$i) {
            $voters[] = $this->getVoter(VoterInterface::ACCESS_GRANTED);
        }

        for ($i = 0; $i < $denies; ++$i) {
            $voters[] = $this->getVoter(VoterInterface::ACCESS_DENIED);
        }

        for ($i = 0; $i < $abstains; ++$i) {
            $voters[] = $this->getVoter(VoterInterface::ACCESS_ABSTAIN);
        }

        return $voters;
    }

    protected function getVoter($vote)
    {
        $voter = $this->getMockBuilder(\Symfony\Component\Security\Core\Authorization\Voter\VoterInterface::class)->getMock();
        $voter->expects($this->any())
              ->method('vote')
              ->willReturn($vote);

        return $voter;
    }

    public function testVotingWrongTypeNoVoteMethod(): void
    {
        $exception = LogicException::class;
        $message = sprintf('"stdClass" should implement the "%s" interface when used as voter.', VoterInterface::class);

        $this->expectException($exception);
        $this->expectExceptionMessage($message);

        $adm = new AccessDecisionManager([new \stdClass()]);
        $token = $this->getMockBuilder(TokenInterface::class)->getMock();

        $adm->decide($token, ['TEST']);
    }

    /**
     * @group legacy
     * @expectedDeprecation Calling vote() on an voter without Symfony\Component\Security\Core\Authorization\Voter\VoterInterface is deprecated as of 3.4 and will be removed in 4.0. Implement the Symfony\Component\Security\Core\Authorization\Voter\VoterInterface on your voter.
     */
    public function testVotingWrongTypeWithVote(): void
    {
        $adm = new AccessDecisionManager([new VoterWithoutInterface()]);
        $token = $this->getMockBuilder(TokenInterface::class)->getMock();

        $adm->decide($token, ['TEST']);
    }
}
