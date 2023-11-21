<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\DataCollector;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Symfony\Component\Validator\Validator\TraceableValidator;
use Symfony\Component\VarDumper\Caster\Caster;
use Symfony\Component\VarDumper\Caster\ClassStub;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Cloner\Stub;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class ValidatorDataCollector extends DataCollector implements LateDataCollectorInterface
{
    private readonly \Symfony\Component\Validator\Validator\TraceableValidator $validator;

    public function __construct(TraceableValidator $validator)
    {
        $this->validator = $validator;
        $this->reset();
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null): void
    {
        // Everything is collected once, on kernel terminate.
    }

    public function reset(): void
    {
        $this->data = [
            'calls' => $this->cloneVar([]),
            'violations_count' => 0,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function lateCollect(): void
    {
        $collected = $this->validator->getCollectedData();
        $this->data['calls'] = $this->cloneVar($collected);
        $this->data['violations_count'] = array_reduce($collected, static fn($previous, $item): float|int => $previous + \count($item['violations']), 0);
    }

    /**
     * @return Data
     */
    public function getCalls()
    {
        return $this->data['calls'];
    }

    /**
     * @return int
     */
    public function getViolationsCount()
    {
        return $this->data['violations_count'];
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'validator';
    }

    protected function getCasters()
    {
        return parent::getCasters() + [
            \Exception::class => static function (\Exception $e, array $a, Stub $s) : array {
                foreach (["\0Exception\0previous", "\0Exception\0trace"] as $k) {
                    if (isset($a[$k])) {
                        unset($a[$k]);
                        ++$s->cut;
                    }
                }
                return $a;
            },
            FormInterface::class => static fn(FormInterface $f, array $a): array => [
                Caster::PREFIX_VIRTUAL.'name' => $f->getName(),
                Caster::PREFIX_VIRTUAL.'type_class' => new ClassStub(\get_class($f->getConfig()->getType()->getInnerType())),
                Caster::PREFIX_VIRTUAL.'data' => $f->getData(),
            ],
        ];
    }
}
