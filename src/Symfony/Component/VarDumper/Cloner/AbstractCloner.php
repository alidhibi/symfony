<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Cloner;

use Symfony\Component\VarDumper\Caster\Caster;
use Symfony\Component\VarDumper\Exception\ThrowingCasterException;

/**
 * AbstractCloner implements a generic caster mechanism for objects and resources.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
abstract class AbstractCloner implements ClonerInterface
{
    public static $defaultCasters = [
        '__PHP_Incomplete_Class' => static fn(\__PHP_Incomplete_Class $c, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\Caster::castPhpIncompleteClass($c, $a, $stub, $isNested),

        \Symfony\Component\VarDumper\Caster\CutStub::class => static fn(\Symfony\Component\VarDumper\Cloner\Stub $c, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\StubCaster::castStub($c, $a, $stub, $isNested),
        \Symfony\Component\VarDumper\Caster\CutArrayStub::class => static fn(\Symfony\Component\VarDumper\Caster\CutArrayStub $c, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\StubCaster::castCutArray($c, $a, $stub, $isNested),
        \Symfony\Component\VarDumper\Caster\ConstStub::class => static fn(\Symfony\Component\VarDumper\Cloner\Stub $c, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\StubCaster::castStub($c, $a, $stub, $isNested),
        \Symfony\Component\VarDumper\Caster\EnumStub::class => static fn(\Symfony\Component\VarDumper\Caster\EnumStub $c, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\StubCaster::castEnum($c, $a, $stub, $isNested),

        'Closure' => static fn(\Closure $c, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested, $filter = 0) => \Symfony\Component\VarDumper\Caster\ReflectionCaster::castClosure($c, $a, $stub, $isNested, $filter),
        'Generator' => static fn(\Generator $c, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\ReflectionCaster::castGenerator($c, $a, $stub, $isNested),
        'ReflectionType' => static fn(\ReflectionType $c, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\ReflectionCaster::castType($c, $a, $stub, $isNested),
        'ReflectionGenerator' => static fn(\ReflectionGenerator $c, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\ReflectionCaster::castReflectionGenerator($c, $a, $stub, $isNested),
        'ReflectionClass' => static fn(\ReflectionClass $c, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested, $filter = 0) => \Symfony\Component\VarDumper\Caster\ReflectionCaster::castClass($c, $a, $stub, $isNested, $filter),
        'ReflectionFunctionAbstract' => static fn(\ReflectionFunctionAbstract $c, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested, $filter = 0) => \Symfony\Component\VarDumper\Caster\ReflectionCaster::castFunctionAbstract($c, $a, $stub, $isNested, $filter),
        'ReflectionMethod' => static fn(\ReflectionMethod $c, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\ReflectionCaster::castMethod($c, $a, $stub, $isNested),
        'ReflectionParameter' => static fn(\ReflectionParameter $c, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\ReflectionCaster::castParameter($c, $a, $stub, $isNested),
        'ReflectionProperty' => static fn(\ReflectionProperty $c, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\ReflectionCaster::castProperty($c, $a, $stub, $isNested),
        'ReflectionExtension' => static fn(\ReflectionExtension $c, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\ReflectionCaster::castExtension($c, $a, $stub, $isNested),
        'ReflectionZendExtension' => static fn(\ReflectionZendExtension $c, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\ReflectionCaster::castZendExtension($c, $a, $stub, $isNested),

        'Doctrine\Common\Persistence\ObjectManager' => static fn($obj, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\StubCaster::cutInternals($obj, $a, $stub, $isNested),
        'Doctrine\Common\Proxy\Proxy' => static fn(\Doctrine\Common\Proxy\Proxy $proxy, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\DoctrineCaster::castCommonProxy($proxy, $a, $stub, $isNested),
        'Doctrine\ORM\Proxy\Proxy' => static fn(\Doctrine\ORM\Proxy\Proxy $proxy, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\DoctrineCaster::castOrmProxy($proxy, $a, $stub, $isNested),
        'Doctrine\ORM\PersistentCollection' => static fn(\Doctrine\ORM\PersistentCollection $coll, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\DoctrineCaster::castPersistentCollection($coll, $a, $stub, $isNested),
        'Doctrine\Persistence\ObjectManager' => static fn($obj, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\StubCaster::cutInternals($obj, $a, $stub, $isNested),

        'DOMException' => static fn(\DOMException $e, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\DOMCaster::castException($e, $a, $stub, $isNested),
        'DOMStringList' => static fn($dom, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\DOMCaster::castLength($dom, $a, $stub, $isNested),
        'DOMNameList' => static fn($dom, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\DOMCaster::castLength($dom, $a, $stub, $isNested),
        'DOMImplementation' => static fn($dom, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\DOMCaster::castImplementation($dom, $a, $stub, $isNested),
        'DOMImplementationList' => static fn($dom, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\DOMCaster::castLength($dom, $a, $stub, $isNested),
        'DOMNode' => static fn(\DOMNode $dom, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\DOMCaster::castNode($dom, $a, $stub, $isNested),
        'DOMNameSpaceNode' => static fn(\DOMNameSpaceNode $dom, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\DOMCaster::castNameSpaceNode($dom, $a, $stub, $isNested),
        'DOMDocument' => static fn(\DOMDocument $dom, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested, $filter = 0) => \Symfony\Component\VarDumper\Caster\DOMCaster::castDocument($dom, $a, $stub, $isNested, $filter),
        'DOMNodeList' => static fn($dom, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\DOMCaster::castLength($dom, $a, $stub, $isNested),
        'DOMNamedNodeMap' => static fn($dom, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\DOMCaster::castLength($dom, $a, $stub, $isNested),
        'DOMCharacterData' => static fn(\DOMCharacterData $dom, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\DOMCaster::castCharacterData($dom, $a, $stub, $isNested),
        'DOMAttr' => static fn(\DOMAttr $dom, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\DOMCaster::castAttr($dom, $a, $stub, $isNested),
        'DOMElement' => static fn(\DOMElement $dom, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\DOMCaster::castElement($dom, $a, $stub, $isNested),
        'DOMText' => static fn(\DOMText $dom, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\DOMCaster::castText($dom, $a, $stub, $isNested),
        'DOMTypeinfo' => static fn(\DOMTypeinfo $dom, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\DOMCaster::castTypeinfo($dom, $a, $stub, $isNested),
        'DOMDomError' => static fn(\DOMDomError $dom, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\DOMCaster::castDomError($dom, $a, $stub, $isNested),
        'DOMLocator' => static fn(\DOMLocator $dom, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\DOMCaster::castLocator($dom, $a, $stub, $isNested),
        'DOMDocumentType' => static fn(\DOMDocumentType $dom, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\DOMCaster::castDocumentType($dom, $a, $stub, $isNested),
        'DOMNotation' => static fn(\DOMNotation $dom, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\DOMCaster::castNotation($dom, $a, $stub, $isNested),
        'DOMEntity' => static fn(\DOMEntity $dom, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\DOMCaster::castEntity($dom, $a, $stub, $isNested),
        'DOMProcessingInstruction' => static fn(\DOMProcessingInstruction $dom, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\DOMCaster::castProcessingInstruction($dom, $a, $stub, $isNested),
        'DOMXPath' => static fn(\DOMXPath $dom, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\DOMCaster::castXPath($dom, $a, $stub, $isNested),

        'XmlReader' => static fn(\XMLReader $reader, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\XmlReaderCaster::castXmlReader($reader, $a, $stub, $isNested),

        'ErrorException' => static fn(\ErrorException $e, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\ExceptionCaster::castErrorException($e, $a, $stub, $isNested),
        'Exception' => static fn(\Exception $e, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested, $filter = 0) => \Symfony\Component\VarDumper\Caster\ExceptionCaster::castException($e, $a, $stub, $isNested, $filter),
        'Error' => static fn(\Error $e, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested, $filter = 0) => \Symfony\Component\VarDumper\Caster\ExceptionCaster::castError($e, $a, $stub, $isNested, $filter),
        \Symfony\Component\DependencyInjection\ContainerInterface::class => static fn($obj, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\StubCaster::cutInternals($obj, $a, $stub, $isNested),
        \Symfony\Component\HttpFoundation\Request::class => static fn(\Symfony\Component\HttpFoundation\Request $request, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\SymfonyCaster::castRequest($request, $a, $stub, $isNested),
        \Symfony\Component\VarDumper\Exception\ThrowingCasterException::class => static fn(\Symfony\Component\VarDumper\Exception\ThrowingCasterException $e, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\ExceptionCaster::castThrowingCasterException($e, $a, $stub, $isNested),
        \Symfony\Component\VarDumper\Caster\TraceStub::class => static fn(\Symfony\Component\VarDumper\Caster\TraceStub $trace, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\ExceptionCaster::castTraceStub($trace, $a, $stub, $isNested),
        \Symfony\Component\VarDumper\Caster\FrameStub::class => static fn(\Symfony\Component\VarDumper\Caster\FrameStub $frame, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\ExceptionCaster::castFrameStub($frame, $a, $stub, $isNested),
        \Symfony\Component\Debug\Exception\SilencedErrorContext::class => static fn(\Symfony\Component\Debug\Exception\SilencedErrorContext $e, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\ExceptionCaster::castSilencedErrorContext($e, $a, $stub, $isNested),

        'PHPUnit_Framework_MockObject_MockObject' => static fn($obj, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\StubCaster::cutInternals($obj, $a, $stub, $isNested),
        'PHPUnit\Framework\MockObject\MockObject' => static fn($obj, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\StubCaster::cutInternals($obj, $a, $stub, $isNested),
        'PHPUnit\Framework\MockObject\Stub' => static fn($obj, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\StubCaster::cutInternals($obj, $a, $stub, $isNested),
        'Prophecy\Prophecy\ProphecySubjectInterface' => static fn($obj, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\StubCaster::cutInternals($obj, $a, $stub, $isNested),
        'Mockery\MockInterface' => static fn($obj, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\StubCaster::cutInternals($obj, $a, $stub, $isNested),

        'PDO' => static fn(\PDO $c, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\PdoCaster::castPdo($c, $a, $stub, $isNested),
        'PDOStatement' => static fn(\PDOStatement $c, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\PdoCaster::castPdoStatement($c, $a, $stub, $isNested),

        'AMQPConnection' => static fn(\AMQPConnection $c, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\AmqpCaster::castConnection($c, $a, $stub, $isNested),
        'AMQPChannel' => static fn(\AMQPChannel $c, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\AmqpCaster::castChannel($c, $a, $stub, $isNested),
        'AMQPQueue' => static fn(\AMQPQueue $c, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\AmqpCaster::castQueue($c, $a, $stub, $isNested),
        'AMQPExchange' => static fn(\AMQPExchange $c, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\AmqpCaster::castExchange($c, $a, $stub, $isNested),
        'AMQPEnvelope' => static fn(\AMQPEnvelope $c, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested, $filter = 0) => \Symfony\Component\VarDumper\Caster\AmqpCaster::castEnvelope($c, $a, $stub, $isNested, $filter),

        'ArrayObject' => static fn(\ArrayObject $c, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\SplCaster::castArrayObject($c, $a, $stub, $isNested),
        'ArrayIterator' => static fn(\ArrayIterator $c, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\SplCaster::castArrayIterator($c, $a, $stub, $isNested),
        'SplDoublyLinkedList' => static fn(\SplDoublyLinkedList $c, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\SplCaster::castDoublyLinkedList($c, $a, $stub, $isNested),
        'SplFileInfo' => static fn(\SplFileInfo $c, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\SplCaster::castFileInfo($c, $a, $stub, $isNested),
        'SplFileObject' => static fn(\SplFileObject $c, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\SplCaster::castFileObject($c, $a, $stub, $isNested),
        'SplHeap' => static fn(\Iterator $c, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\SplCaster::castHeap($c, $a, $stub, $isNested),
        'SplObjectStorage' => static fn(\SplObjectStorage $c, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\SplCaster::castObjectStorage($c, $a, $stub, $isNested),
        'SplPriorityQueue' => static fn(\Iterator $c, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\SplCaster::castHeap($c, $a, $stub, $isNested),
        'OuterIterator' => static fn(\OuterIterator $c, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\SplCaster::castOuterIterator($c, $a, $stub, $isNested),

        'MongoCursorInterface' => static fn(\MongoCursorInterface $cursor, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\MongoCaster::castCursor($cursor, $a, $stub, $isNested),

        'Redis' => static fn(\Redis $c, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\RedisCaster::castRedis($c, $a, $stub, $isNested),
        'RedisArray' => static fn(\RedisArray $c, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\RedisCaster::castRedisArray($c, $a, $stub, $isNested),

        'DateTimeInterface' => static fn(\DateTimeInterface $d, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested, $filter) => \Symfony\Component\VarDumper\Caster\DateCaster::castDateTime($d, $a, $stub, $isNested, $filter),
        'DateInterval' => static fn(\DateInterval $interval, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested, $filter) => \Symfony\Component\VarDumper\Caster\DateCaster::castInterval($interval, $a, $stub, $isNested, $filter),
        'DateTimeZone' => static fn(\DateTimeZone $timeZone, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested, $filter) => \Symfony\Component\VarDumper\Caster\DateCaster::castTimeZone($timeZone, $a, $stub, $isNested, $filter),
        'DatePeriod' => static fn(\DatePeriod $p, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested, $filter) => \Symfony\Component\VarDumper\Caster\DateCaster::castPeriod($p, $a, $stub, $isNested, $filter),

        'CurlHandle' => static fn($h, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested): array => \Symfony\Component\VarDumper\Caster\ResourceCaster::castCurl($h, $a, $stub, $isNested),
        ':curl' => static fn($h, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested): array => \Symfony\Component\VarDumper\Caster\ResourceCaster::castCurl($h, $a, $stub, $isNested),

        ':dba' => static fn($dba, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\ResourceCaster::castDba($dba, $a, $stub, $isNested),
        ':dba persistent' => static fn($dba, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\ResourceCaster::castDba($dba, $a, $stub, $isNested),
        ':gd' => static fn($gd, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\ResourceCaster::castGd($gd, $a, $stub, $isNested),
        ':mysql link' => static fn($h, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\ResourceCaster::castMysqlLink($h, $a, $stub, $isNested),
        ':pgsql large object' => static fn($lo, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\PgSqlCaster::castLargeObject($lo, $a, $stub, $isNested),
        ':pgsql link' => static fn($link, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\PgSqlCaster::castLink($link, $a, $stub, $isNested),
        ':pgsql link persistent' => static fn($link, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\PgSqlCaster::castLink($link, $a, $stub, $isNested),
        ':pgsql result' => static fn($result, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\PgSqlCaster::castResult($result, $a, $stub, $isNested),
        ':process' => static fn($process, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\ResourceCaster::castProcess($process, $a, $stub, $isNested),
        ':stream' => static fn($stream, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\ResourceCaster::castStream($stream, $a, $stub, $isNested),
        ':persistent stream' => static fn($stream, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\ResourceCaster::castStream($stream, $a, $stub, $isNested),
        ':stream-context' => static fn($stream, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\ResourceCaster::castStreamContext($stream, $a, $stub, $isNested),
        ':xml' => static fn($h, array $a, \Symfony\Component\VarDumper\Cloner\Stub $stub, $isNested) => \Symfony\Component\VarDumper\Caster\XmlResourceCaster::castXml($h, $a, $stub, $isNested),
    ];

    protected $maxItems = 2500;

    protected $maxString = -1;

    protected $minDepth = 1;

    protected bool $useExt;

    private array $casters = [];

    private $prevErrorHandler;

    private array $classInfo = [];

    private int $filter = 0;

    /**
     * @param callable[]|null $casters A map of casters
     *
     * @see addCasters
     */
    public function __construct(array $casters = null)
    {
        if (null === $casters) {
            $casters = static::$defaultCasters;
        }

        $this->addCasters($casters);
        $this->useExt = \extension_loaded('symfony_debug');
    }

    /**
     * Adds casters for resources and objects.
     *
     * Maps resources or objects types to a callback.
     * Types are in the key, with a callable caster for value.
     * Resource types are to be prefixed with a `:`,
     * see e.g. static::$defaultCasters.
     *
     * @param callable[] $casters A map of casters
     */
    public function addCasters(array $casters): void
    {
        foreach ($casters as $type => $callback) {
            $this->casters[strtolower($type)][] = \is_string($callback) && false !== strpos($callback, '::') ? explode('::', $callback, 2) : $callback;
        }
    }

    /**
     * Sets the maximum number of items to clone past the minimum depth in nested structures.
     *
     * @param int $maxItems
     */
    public function setMaxItems($maxItems): void
    {
        $this->maxItems = (int) $maxItems;
    }

    /**
     * Sets the maximum cloned length for strings.
     *
     * @param int $maxString
     */
    public function setMaxString($maxString): void
    {
        $this->maxString = (int) $maxString;
    }

    /**
     * Sets the minimum tree depth where we are guaranteed to clone all the items.  After this
     * depth is reached, only setMaxItems items will be cloned.
     *
     * @param int $minDepth
     */
    public function setMinDepth($minDepth): void
    {
        $this->minDepth = (int) $minDepth;
    }

    /**
     * Clones a PHP variable.
     *
     * @param mixed $var    Any PHP variable
     * @param int   $filter A bit field of Caster::EXCLUDE_* constants
     *
     * @return Data The cloned variable represented by a Data object
     */
    public function cloneVar($var, $filter = 0)
    {
        $this->prevErrorHandler = set_error_handler(function ($type, $msg, $file, $line, $context = []) {
            if (\E_RECOVERABLE_ERROR === $type || \E_USER_ERROR === $type) {
                // Cloner never dies
                throw new \ErrorException($msg, 0, $type, $file, $line);
            }

            if ($this->prevErrorHandler) {
                return \call_user_func($this->prevErrorHandler, $type, $msg, $file, $line, $context);
            }

            return false;
        });
        $this->filter = $filter;

        if ($gc = gc_enabled()) {
            gc_disable();
        }

        try {
            return new Data($this->doClone($var));
        } finally {
            if ($gc) {
                gc_enable();
            }

            restore_error_handler();
            $this->prevErrorHandler = null;
        }
    }

    /**
     * Effectively clones the PHP variable.
     *
     * @param mixed $var Any PHP variable
     *
     * @return array The cloned variable represented in an array
     */
    abstract protected function doClone($var);

    /**
     * Casts an object to an array representation.
     *
     * @param Stub $stub     The Stub for the casted object
     * @param bool $isNested True if the object is nested in the dumped structure
     *
     * @return array The object casted as array
     */
    protected function castObject(Stub $stub, $isNested)
    {
        $obj = $stub->value;
        $class = $stub->class;

        if ((\PHP_VERSION_ID >= 80000 || (isset($class[15]) && "\0" === $class[15])) && false !== strpos($class, "@anonymous\0")) {
            $stub->class = \PHP_VERSION_ID < 80000 ? ((get_parent_class($class) ?: key(class_implements($class))) ?: 'class').'@anonymous' : get_debug_type($obj);
        }

        if (isset($this->classInfo[$class])) {
            list($i, $parents, $hasDebugInfo) = $this->classInfo[$class];
        } else {
            $i = 2;
            $parents = [strtolower($class)];
            $hasDebugInfo = method_exists($class, '__debugInfo');

            foreach (class_parents($class) as $p) {
                $parents[] = strtolower($p);
                ++$i;
            }

            foreach (class_implements($class) as $p) {
                $parents[] = strtolower($p);
                ++$i;
            }

            $parents[] = '*';

            $this->classInfo[$class] = [$i, $parents, $hasDebugInfo];
        }

        $a = Caster::castObject($obj, $class, $hasDebugInfo, $stub->class);

        try {
            while ($i--) {
                if (!empty($this->casters[$p = $parents[$i]])) {
                    foreach ($this->casters[$p] as $callback) {
                        $a = $callback($obj, $a, $stub, $isNested, $this->filter);
                    }
                }
            }
        } catch (\Exception $exception) {
            $a = [(Stub::TYPE_OBJECT === $stub->type ? Caster::PREFIX_VIRTUAL : '').'⚠' => new ThrowingCasterException($exception)] + $a;
        }

        return $a;
    }

    /**
     * Casts a resource to an array representation.
     *
     * @param Stub $stub     The Stub for the casted resource
     * @param bool $isNested True if the object is nested in the dumped structure
     *
     * @return array The resource casted as array
     */
    protected function castResource(Stub $stub, $isNested)
    {
        $a = [];
        $res = $stub->value;
        $type = $stub->class;

        try {
            if (!empty($this->casters[':'.$type])) {
                foreach ($this->casters[':'.$type] as $callback) {
                    $a = $callback($res, $a, $stub, $isNested, $this->filter);
                }
            }
        } catch (\Exception $exception) {
            $a = [(Stub::TYPE_OBJECT === $stub->type ? Caster::PREFIX_VIRTUAL : '').'⚠' => new ThrowingCasterException($exception)] + $a;
        }

        return $a;
    }
}
