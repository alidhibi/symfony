<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Validator;

use Symfony\Component\Form\FormTypeGuesserInterface;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\Guess\ValueGuess;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Mapping\ClassMetadataInterface;
use Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface;

class ValidatorTypeGuesser implements FormTypeGuesserInterface
{
    private readonly \Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface $metadataFactory;

    public function __construct(MetadataFactoryInterface $metadataFactory)
    {
        $this->metadataFactory = $metadataFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function guessType($class, $property)
    {
        return $this->guess($class, $property, fn(Constraint $constraint) => $this->guessTypeForConstraint($constraint));
    }

    /**
     * {@inheritdoc}
     */
    public function guessRequired($class, $property)
    {
        return $this->guess($class, $property, function (Constraint $constraint) {
            return $this->guessRequiredForConstraint($constraint);
        // If we don't find any constraint telling otherwise, we can assume
        // that a field is not required (with LOW_CONFIDENCE)
        }, false);
    }

    /**
     * {@inheritdoc}
     */
    public function guessMaxLength($class, $property)
    {
        return $this->guess($class, $property, fn(Constraint $constraint) => $this->guessMaxLengthForConstraint($constraint));
    }

    /**
     * {@inheritdoc}
     */
    public function guessPattern($class, $property)
    {
        return $this->guess($class, $property, fn(Constraint $constraint) => $this->guessPatternForConstraint($constraint));
    }

    /**
     * Guesses a field class name for a given constraint.
     *
     * @return TypeGuess|null The guessed field class and options
     */
    public function guessTypeForConstraint(Constraint $constraint): ?\Symfony\Component\Form\Guess\TypeGuess
    {
        switch (\get_class($constraint)) {
            case \Symfony\Component\Validator\Constraints\Type::class:
                switch ($constraint->type) {
                    case 'array':
                        return new TypeGuess(\Symfony\Component\Form\Extension\Core\Type\CollectionType::class, [], Guess::MEDIUM_CONFIDENCE);
                    case 'boolean':
                    case 'bool':
                        return new TypeGuess(\Symfony\Component\Form\Extension\Core\Type\CheckboxType::class, [], Guess::MEDIUM_CONFIDENCE);

                    case 'double':
                    case 'float':
                    case 'numeric':
                    case 'real':
                        return new TypeGuess(\Symfony\Component\Form\Extension\Core\Type\NumberType::class, [], Guess::MEDIUM_CONFIDENCE);

                    case 'integer':
                    case 'int':
                    case 'long':
                        return new TypeGuess(\Symfony\Component\Form\Extension\Core\Type\IntegerType::class, [], Guess::MEDIUM_CONFIDENCE);

                    case \DateTime::class:
                    case '\DateTime':
                        return new TypeGuess(\Symfony\Component\Form\Extension\Core\Type\DateType::class, [], Guess::MEDIUM_CONFIDENCE);

                    case 'string':
                        return new TypeGuess(\Symfony\Component\Form\Extension\Core\Type\TextType::class, [], Guess::LOW_CONFIDENCE);
                }

                break;

            case \Symfony\Component\Validator\Constraints\Country::class:
                return new TypeGuess(\Symfony\Component\Form\Extension\Core\Type\CountryType::class, [], Guess::HIGH_CONFIDENCE);

            case \Symfony\Component\Validator\Constraints\Currency::class:
                return new TypeGuess(\Symfony\Component\Form\Extension\Core\Type\CurrencyType::class, [], Guess::HIGH_CONFIDENCE);

            case \Symfony\Component\Validator\Constraints\Date::class:
                return new TypeGuess(\Symfony\Component\Form\Extension\Core\Type\DateType::class, ['input' => 'string'], Guess::HIGH_CONFIDENCE);

            case \Symfony\Component\Validator\Constraints\DateTime::class:
                return new TypeGuess(\Symfony\Component\Form\Extension\Core\Type\DateTimeType::class, ['input' => 'string'], Guess::HIGH_CONFIDENCE);

            case \Symfony\Component\Validator\Constraints\Email::class:
                return new TypeGuess(\Symfony\Component\Form\Extension\Core\Type\EmailType::class, [], Guess::HIGH_CONFIDENCE);

            case \Symfony\Component\Validator\Constraints\File::class:
            case \Symfony\Component\Validator\Constraints\Image::class:
                return new TypeGuess(\Symfony\Component\Form\Extension\Core\Type\FileType::class, [], Guess::HIGH_CONFIDENCE);

            case \Symfony\Component\Validator\Constraints\Language::class:
                return new TypeGuess(\Symfony\Component\Form\Extension\Core\Type\LanguageType::class, [], Guess::HIGH_CONFIDENCE);

            case \Symfony\Component\Validator\Constraints\Locale::class:
                return new TypeGuess(\Symfony\Component\Form\Extension\Core\Type\LocaleType::class, [], Guess::HIGH_CONFIDENCE);

            case \Symfony\Component\Validator\Constraints\Time::class:
                return new TypeGuess(\Symfony\Component\Form\Extension\Core\Type\TimeType::class, ['input' => 'string'], Guess::HIGH_CONFIDENCE);

            case \Symfony\Component\Validator\Constraints\Url::class:
                return new TypeGuess(\Symfony\Component\Form\Extension\Core\Type\UrlType::class, [], Guess::HIGH_CONFIDENCE);

            case \Symfony\Component\Validator\Constraints\Ip::class:
                return new TypeGuess(\Symfony\Component\Form\Extension\Core\Type\TextType::class, [], Guess::MEDIUM_CONFIDENCE);

            case \Symfony\Component\Validator\Constraints\Length::class:
            case \Symfony\Component\Validator\Constraints\Regex::class:
                return new TypeGuess(\Symfony\Component\Form\Extension\Core\Type\TextType::class, [], Guess::LOW_CONFIDENCE);

            case \Symfony\Component\Validator\Constraints\Range::class:
                return new TypeGuess(\Symfony\Component\Form\Extension\Core\Type\NumberType::class, [], Guess::LOW_CONFIDENCE);

            case \Symfony\Component\Validator\Constraints\Count::class:
                return new TypeGuess(\Symfony\Component\Form\Extension\Core\Type\CollectionType::class, [], Guess::LOW_CONFIDENCE);

            case \Symfony\Component\Validator\Constraints\IsTrue::class:
            case \Symfony\Component\Validator\Constraints\IsFalse::class:
                return new TypeGuess(\Symfony\Component\Form\Extension\Core\Type\CheckboxType::class, [], Guess::MEDIUM_CONFIDENCE);
        }

        return null;
    }

    /**
     * Guesses whether a field is required based on the given constraint.
     *
     * @return ValueGuess|null The guess whether the field is required
     */
    public function guessRequiredForConstraint(Constraint $constraint): ?\Symfony\Component\Form\Guess\ValueGuess
    {
        switch (\get_class($constraint)) {
            case \Symfony\Component\Validator\Constraints\NotNull::class:
            case \Symfony\Component\Validator\Constraints\NotBlank::class:
            case \Symfony\Component\Validator\Constraints\IsTrue::class:
                return new ValueGuess(true, Guess::HIGH_CONFIDENCE);
        }

        return null;
    }

    /**
     * Guesses a field's maximum length based on the given constraint.
     *
     * @return ValueGuess|null The guess for the maximum length
     */
    public function guessMaxLengthForConstraint(Constraint $constraint): ?\Symfony\Component\Form\Guess\ValueGuess
    {
        switch (\get_class($constraint)) {
            case \Symfony\Component\Validator\Constraints\Length::class:
                if (is_numeric($constraint->max)) {
                    return new ValueGuess($constraint->max, Guess::HIGH_CONFIDENCE);
                }

                break;

            case \Symfony\Component\Validator\Constraints\Type::class:
                if (\in_array($constraint->type, ['double', 'float', 'numeric', 'real'])) {
                    return new ValueGuess(null, Guess::MEDIUM_CONFIDENCE);
                }

                break;

            case \Symfony\Component\Validator\Constraints\Range::class:
                if (is_numeric($constraint->max)) {
                    return new ValueGuess(\strlen((string) $constraint->max), Guess::LOW_CONFIDENCE);
                }

                break;
        }

        return null;
    }

    /**
     * Guesses a field's pattern based on the given constraint.
     *
     * @return ValueGuess|null The guess for the pattern
     */
    public function guessPatternForConstraint(Constraint $constraint): ?\Symfony\Component\Form\Guess\ValueGuess
    {
        switch (\get_class($constraint)) {
            case \Symfony\Component\Validator\Constraints\Length::class:
                if (is_numeric($constraint->min)) {
                    return new ValueGuess(sprintf('.{%s,}', (string) $constraint->min), Guess::LOW_CONFIDENCE);
                }

                break;

            case \Symfony\Component\Validator\Constraints\Regex::class:
                $htmlPattern = $constraint->getHtmlPattern();

                if (null !== $htmlPattern) {
                    return new ValueGuess($htmlPattern, Guess::HIGH_CONFIDENCE);
                }

                break;

            case \Symfony\Component\Validator\Constraints\Range::class:
                if (is_numeric($constraint->min)) {
                    return new ValueGuess(sprintf('.{%s,}', \strlen((string) $constraint->min)), Guess::LOW_CONFIDENCE);
                }

                break;

            case \Symfony\Component\Validator\Constraints\Type::class:
                if (\in_array($constraint->type, ['double', 'float', 'numeric', 'real'])) {
                    return new ValueGuess(null, Guess::MEDIUM_CONFIDENCE);
                }

                break;
        }

        return null;
    }

    /**
     * Iterates over the constraints of a property, executes a constraints on
     * them and returns the best guess.
     *
     * @param string   $class        The class to read the constraints from
     * @param string   $property     The property for which to find constraints
     * @param \Closure $closure      The closure that returns a guess
     *                               for a given constraint
     * @param mixed    $defaultValue The default value assumed if no other value
     *                               can be guessed
     *
     * @return Guess|null The guessed value with the highest confidence
     */
    protected function guess($class, $property, \Closure $closure, $defaultValue = null)
    {
        $guesses = [];
        $classMetadata = $this->metadataFactory->getMetadataFor($class);

        if ($classMetadata instanceof ClassMetadataInterface && $classMetadata->hasPropertyMetadata($property)) {
            foreach ($classMetadata->getPropertyMetadata($property) as $memberMetadata) {
                foreach ($memberMetadata->getConstraints() as $constraint) {
                    if ($guess = $closure($constraint)) {
                        $guesses[] = $guess;
                    }
                }
            }
        }

        if (null !== $defaultValue) {
            $guesses[] = new ValueGuess($defaultValue, Guess::LOW_CONFIDENCE);
        }

        return Guess::getBestGuess($guesses);
    }
}
