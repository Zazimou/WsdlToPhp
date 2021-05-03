<?php declare(strict_types=1);


namespace Zazimou\WsdlToPhp\PhpGenerators;


use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\Property;
use Nette\Utils\Strings;
use ReflectionMethod;
use Zazimou\WsdlToPhp\Exceptions\UnexpectedValueException;
use Zazimou\WsdlToPhp\Options\GeneratorOptions;
use Zazimou\WsdlToPhp\Printer;
use Zazimou\WsdlToPhp\Types\Wsdl\Element;


class BasePhpGenerator
{
    /** @var GeneratorOptions */
    public $options;

    /** @var string */
    public $filePath;

    /** @var string */
    public $namespace;

    public function __construct(GeneratorOptions $options)
    {
        $this->options = $options;
    }


    /**
     * @return PhpFile
     */
    protected function createFile(): PhpFile
    {
        $phpFile = new PhpFile;
        $phpFile->setStrictTypes();
        $phpFile->setComment('This file has autogenerated stub. Please remember if you edit this file.');
        $phpFile->addNamespace($this->namespace);

        return $phpFile;
    }

    /**
     * @param string $path
     * @param int    $chmod
     * @param bool   $recursive
     * @throws UnexpectedValueException
     */
    public static function createDir(string $path, int $chmod = 0666, bool $recursive = false): void
    {
        if (!is_dir($path)) {
            $isCreated = mkdir($path, $chmod, $recursive);
            if ($isCreated == false) {
                throw new UnexpectedValueException(printf('Creating path %s failure.', $path));
            }
        }
    }

    /**
     * @param string  $name
     * @param PhpFile $phpFile
     * @throws UnexpectedValueException
     */
    public function printClass(string $name, PhpFile $phpFile): void
    {
        $printer = new Printer;
        $file = $printer->printFile($phpFile);
        self::generateToFile($this->filePath, $name, $file);
    }

    /**
     * @param string $filePath
     * @param string $className
     * @param string $content
     * @throws UnexpectedValueException
     */
    public static function generateToFile(string $filePath, string $className, string $content): void
    {
        self::createDir($filePath, 0666, true);
        $file = fopen($filePath.DIRECTORY_SEPARATOR.$className.'.php', 'w');
        fwrite($file, $content);
    }

    protected function normalizePropertyDocComment(Element $element): ?string
    {
        if ($element->type === 'base64Binary') {
            return 'Contains base64Binary string';
        }

        return null;
    }

    /**
     * @param Element $element
     * @return string
     */
    protected function normalizePropertyType(Element $element): string
    {
        $type = $element->type;
        if ($type == 'dateTime') {
            $type = 'DateTime';
        }
        if ($type == 'base64Binary') {
            $type = 'string';
        }
        if ($type == 'boolean') {
            $type = 'bool';
        }
        if ($element->arrayable) {
            $type = $type.'[]';
        }
        if ($element->nullable) {
            $type = $type.'|null';
        }

        return $type;
    }

    /**
     * @param Property $property
     * @param Element  $element
     */
    protected function resolvePropertyTypeByPhpVersion(Property $property, Element $element): void
    {
        $phpVersion = $this->options->phpVersion;
        $type = $this->normalizePropertyType($element);
        if ((float)$phpVersion < 7.4) {
            $property->addComment('@var '.$this->normalizePropertyType($element));
        } else {
            if (Strings::contains($type, '[]')) {
                $property->addComment('@var '.$this->normalizePropertyType($element));
                $property->setType('array');
            } else {
                $property->setType($this->namespace.'\\'.$this->normalizePropertyType($element));
            }
        }
    }

    public static function getVisibilityFromReflection(ReflectionMethod $reflection): string
    {
        if ($reflection->isPublic() === true) {
            return 'public';
        }
        if ($reflection->isProtected() === true) {
            return 'protected';
        }
        if ($reflection->isPrivate() === true) {
            return 'private';
        }

        return 'public';
    }

    /**
     * @param ReflectionMethod $method
     * @return string
     */
    protected function getMethodBody(ReflectionMethod $method): string
    {
        $fileName = $method->getFileName();
        $startLine = $method->getStartLine() + 1;
        $endLine = $method->getEndLine() - 1;

        $source = file($fileName);
        $source = implode('', array_slice($source, 0, count($source)));
        $source = preg_split("/".PHP_EOL."/", $source);

        $body = '';
        for ($i = $startLine; $i < $endLine; $i++) {
            $beforeSubstr = "{$source[$i]}\n";
            $length = Strings::length($beforeSubstr);
            $afterSubstr = mb_substr($beforeSubstr, 4, $length - 4);
            if ($afterSubstr == '') {
                $afterSubstr = "\n";
            }
            $body .= ($afterSubstr);
        }

        return $body;
    }
}