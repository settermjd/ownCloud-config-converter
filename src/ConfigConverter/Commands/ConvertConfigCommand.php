<?php
/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2014 Morris Jobke <hey@morrisjobke.de>
 * Copyright (c) 2017 Matthew Setter <matthew@matthewsetter.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */
namespace ConfigConverter\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ConvertConfigCommand
 * It extracts the code comments out of ownCloud's config/config.sample.php and creates a RST document.
 *
 * @package ConfigConverter\Commands
 */
class ConvertConfigCommand extends Command
{
    /**
     * The core of the command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->convertFile(
            $input->getOption('input-file'),
            $input->getOption('output-file'),
            $input->getOption('tag')
        );
    }

    /**
     * Configures the command
     */
    protected function configure()
    {
        $this
            ->setName('config:convert')
            ->setDescription('Converts config.sample.php to config_sample_php_parameters.rst')
            ->setDefinition([
                new InputOption('input-file', 'i', InputOption::VALUE_REQUIRED,
                    'The location of config.sample.php'),
                new InputOption('output-file', 'o', InputOption::VALUE_REQUIRED,
                    'The location of config_sample_php_parameters.rst'),
                new InputOption('tag', 't', InputOption::VALUE_OPTIONAL,
                    'Tag to use for copying a config entry (default: see)'),
            ])
            ->setHelp('Converts config.sample.php to config_sample_php_parameters.rst');
    }

    /**
     * @param $string
     * @return mixed|string
     */
    public function escapeRST($string)
    {
        # just replace all \ by \\ if there is no code block present
        if (strpos($string, '``') === false) {
            return str_replace('\\', '\\\\', $string);
        }

        $parts = explode('``', $string);

        foreach ($parts as $key => &$part) {
            # just even parts are outside of the code block
            # example:
            #
            # 	Test code: ``$my = $code + 5;`` shows that ...
            #
            # The code part has the id 1 and is an odd number
            if ($key % 2 == 0) {
                str_replace('\\', '\\\\', $part);
            }
        }

        return implode('``', $parts);
    }

    /**
     * Convert the sample config file to an RST documentation equivalent
     *
     * @param string $inputFile
     * @param string $outputFile
     * @param string $tag
     * returns string
     */
    public function convertFile($inputFile, $outputFile, $tag)
    {
        $docBlock = file_get_contents($inputFile);

        /* trim everything before this (including itself) */
        $start = '$CONFIG = array(';
        $docBlock = substr($docBlock, strpos($docBlock, $start) + strlen($start));

        // trim the end of the config variable
        $end = ');';
        $docBlock = substr($docBlock, 0, strrpos($docBlock, $end));

        // split on '/**'
        $blocks = explode('/**', $docBlock);

        // output that gets written to the file
        $output = '';
        $outputFirstParagraph = '';

        // array that holds all RST representations of all config options to copy them
        $lookup = [];

        // check if the current processed block is the first section (first call sets
        // this to true and all other sections to false)
        $isFirstSection = null;

        foreach ($blocks as $block) {
            if (trim($block) === '') {
                continue;
            }
            $block = '/**' . $block;
            $parts = explode(' */', $block);
            $id = null;
            $doc = '';
            $code = '';
            // there should be exactly two parts after the split - otherwise there are
            // some mistakes in the parsed block
            if (count($parts) !== 2) {
                echo '<h3>Uncommon part count!</h3><pre>';
                print_r($parts);
                echo '</pre>';
            } else {
                $doc = $parts[0] . ' */';
                $code = $parts[1];
            }

            /*
            * This checks if there is a config option below the comment (should be one
            * if there is a config option or none if the comment is just a heading of
            * the next section
            */
            preg_match('!^\'([^\']*)\'!m', $block, $matches);
            if (!in_array(count($matches), [0, 2])) {
                echo 'Uncommon matches count<pre>';
                print_r($matches);
                echo '</pre>';
            }

            // if there are two matches a config option was found -> set it as ID
            if (count($matches) === 2) {
                $id = $matches[1];
            }

            // parse the doc block
            $phpdoc = new \phpDocumentor\Reflection\DocBlock($doc);

            // check for tagged elements to replace the tag with the actual config
            // description
            $references = $phpdoc->getTagsByName($tag);
            if (!empty($references)) {
                foreach ($references as $reference) {
                    $name = $reference->getContent();
                    if (array_key_exists($name, $lookup)) {
                        // append the element at the current position
                        $output .= $lookup[$name];
                    }
                }
            }

            $RSTRepresentation = '';

            // generate RST output
            if (is_null($id)) {
                // print heading - no
                $heading = $phpdoc->getShortDescription();
                $RSTRepresentation .= "\n" . $heading . "\n";
                $RSTRepresentation .= str_repeat('-', strlen($heading)) . "\n\n";
                $longDescription = $phpdoc->getLongDescription();
                if (trim($longDescription) !== '') {
                    $RSTRepresentation .= $longDescription . "\n\n";
                }
                if ($isFirstSection === null) {
                    $isFirstSection = true;
                } else {
                    $isFirstSection = false;
                }
            } else {
                // mark as literal (code block)
                $RSTRepresentation .= "\n::\n\n";
                // trim whitespace
                $code = trim($code);
                // intend every line by an tab - also trim whitespace
                // (for example: empty lines at the end)
                foreach (explode("\n", trim($code)) as $line) {
                    $RSTRepresentation .= "\t" . $line . "\n";
                }
                $RSTRepresentation .= "\n";
                // print description
                $RSTRepresentation .= $this->escapeRST($phpdoc->getText());
                // empty line
                $RSTRepresentation .= "\n";

                $lookup[$id] = $RSTRepresentation;
            }

            if ($isFirstSection) {
                $outputFirstParagraph .= $RSTRepresentation;
            } else {
                $output .= $RSTRepresentation;
            }
        }

        $configDocumentation = file_get_contents($outputFile);
        $configDocumentationOutput = '';

        $tmp = explode('DEFAULT_SECTION_START', $configDocumentation);
        if (count($tmp) !== 2) {
            print("There are not exactly one DEFAULT_SECTION_START in the config documentation\n");
            exit();
        }

        $configDocumentationOutput .= $tmp[0];

        // append start placeholder
        $configDocumentationOutput .= "DEFAULT_SECTION_START\n\n";

        // append first paragraph
        $configDocumentationOutput .= $outputFirstParagraph;

        // append end placeholder
        $configDocumentationOutput .= "\n.. DEFAULT_SECTION_END";

        $tmp = explode('DEFAULT_SECTION_END', $tmp[1]);
        if (count($tmp) !== 2) {
            print("There are not exactly one DEFAULT_SECTION_END in the config documentation\n");
            exit();
        }

        // drop the first part (old generated documentation which should be overwritten by this script) and just process
        $tmp = explode('ALL_OTHER_SECTIONS_START', $tmp[1]);
        if (count($tmp) !== 2) {
            print("There are not exactly one ALL_OTHER_SECTIONS_START in the config documentation\n");
            exit();
        }

        // append middle part between DEFAULT_SECTION_END and ALL_OTHER_SECTIONS_START
        $configDocumentationOutput .= $tmp[0];

        // append start placeholder
        $configDocumentationOutput .= "ALL_OTHER_SECTIONS_START\n\n";

        // append rest of generated code
        $configDocumentationOutput .= $output;

        // drop the first part (old generated documentation which should be overwritten
        // by  this script) and just process
        $tmp = explode('ALL_OTHER_SECTIONS_END', $tmp[1]);
        if (count($tmp) !== 2) {
            print("There are not exactly one ALL_OTHER_SECTIONS_END in the config documentation\n");
            exit();
        }

        // append end placeholder
        $configDocumentationOutput .= "\n.. ALL_OTHER_SECTIONS_END";
        $configDocumentationOutput .= $tmp[1];

        /* write content to file */
        file_put_contents($outputFile, $configDocumentationOutput);
    }
}
