<?php

namespace ConfigConverterTest;

use AntoraTools\Command\GenerateAsciiDocBookFileCommand;
use ConfigConverter\Commands\ConvertConfigToAsciiDocFormatCommand;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\{
    Application,
    Tester\CommandTester
};
use Twig_Environment;
use Twig_Loader_Filesystem;
use Zend\Expressive\Twig\TwigRenderer;

class ConvertConfigToAsciiDocFormatCommandTest extends TestCase
{
    private $fileSystem;

    public function setUp()
    {
        $directory = [
            'opt' => [
                'core' => [
                    'config' => [

                    ]
                ]
            ]
        ];

        // setup and cache the virtual file system
        $this->fileSystem = vfsStream::setup('/', null, $directory);

        vfsStream::newFile('config.sample.php')
            ->at($this->fileSystem->getChild('opt/core/config'))
            ->setContent(file_get_contents(__DIR__ . '/../data/config.sample.php'));

        vfsStream::newFile('config_sample_php_parameters.adoc')
            ->at($this->fileSystem->getChild('opt/core/config'))
            ->setContent(file_get_contents(__DIR__ . '/../data/config_sample_php_parameters.adoc'));
    }

    public function buildRowItemDataProvider()
    {
        $headerBlockRawData =<<<EOF
    /**
     * Default Parameters
     *
     * These parameters are configured by the ownCloud installer, and are required
     * for your ownCloud server to operate.
     */

EOF;

        $headerBlockParsedData = [
            'summary' => 'Default Parameters',
            'description' => 'These parameters are configured by the ownCloud installer, and are required
for your ownCloud server to operate.',
            'section_header' => true,
            'code' => null,
        ];

        $rowWithCodeRawData =<<<EOF
    /**
     * trusted_domains
     *
     * Your list of trusted domains that users can log into. Specifying trusted
     * domains prevents host header poisoning. Do not remove this, as it performs
     * necessary security checks. Please consider that for backend processes like
     * background jobs or occ commands, the url parameter in key ``overwrite.cli.url``
     * is used. For more details please see that key.
     */

EOF;

        $codeBlock = "'trusted_domains' => [
            'demo.example.org',
            'otherdomain.example.org',
        ]";

        $rowWithCodeParsedData = [
            'summary' => 'trusted_domains',
            'description' => 'Your list of trusted domains that users can log into. Specifying trusted
domains prevents host header poisoning. Do not remove this, as it performs
necessary security checks. Please consider that for backend processes like
background jobs or occ commands, the url parameter in key `overwrite.cli.url`
is used. For more details please see that key.',
            'section_header' => false,
            'code' => "'trusted_domains' => [
        'demo.example.org',
        'otherdomain.example.org',
    ]",
        ];

        $rowWithoutCodeRawData =<<<EOF
    /**
     * trusted_domains
     *
     * Your list of trusted domains that users can log into. Specifying trusted
     * domains prevents host header poisoning. Do not remove this, as it performs
     * necessary security checks. Please consider that for backend processes like
     * background jobs or occ commands, the url parameter in key ``overwrite.cli.url``
     * is used. For more details please see that key.
     */

EOF;

        $rowWithoutCodeParsedData = [
            'summary' => 'trusted_domains',
            'description' => 'Your list of trusted domains that users can log into. Specifying trusted
domains prevents host header poisoning. Do not remove this, as it performs
necessary security checks. Please consider that for backend processes like
background jobs or occ commands, the url parameter in key `overwrite.cli.url`
is used. For more details please see that key.',
            'section_header' => true,
            'code' => null,
        ];

        $blockWithBulletPointRawData =<<<EOF
/**
         * dbtype
         *
         * Identifies the database used with this installation. See also config option
         * ``supportedDatabases``.
         * Supported databases:
         *
         * 	- mysql (MySQL/MariaDB)
         * 	- pgsql (PostgreSQL)
         * 	- sqlite (SQLite3 - Not in Enterprise Edition)
         * 	- oci (Oracle - Enterprise Edition Only)
         */
EOF;

        $blockWithBulletPointParsedData = [
            'summary' => 'dbtype',
            'description' => 'Identifies the database used with this installation. See also config option
`supportedDatabases`.
Supported databases:

- mysql (MySQL/MariaDB)
- pgsql (PostgreSQL)
- sqlite (SQLite3 - Not in Enterprise Edition)
- oci (Oracle - Enterprise Edition Only)',
            'section_header' => true,
            'code' => null,
        ];

        return [
            [$headerBlockRawData, null, $headerBlockParsedData],
            [$rowWithCodeRawData, $codeBlock, $rowWithCodeParsedData],
            [$rowWithoutCodeRawData, null, $rowWithoutCodeParsedData],
            [$blockWithBulletPointRawData, null, $blockWithBulletPointParsedData],
        ];
    }

    /**
     * @dataProvider buildRowItemDataProvider
     * @param string $rawData
     * @param string $codeBlock
     * @param array $parsedData
     */
    public function testCanBuildRowItemCorrectly($rawData, $codeBlock, $parsedData = [])
    {
       $command = new ConvertConfigToAsciiDocFormatCommand();
       $templateData = $command->buildRowItem($rawData, $codeBlock);
       $this->assertSame($parsedData, $templateData);
    }

    public function parseBlockDataProvider()
    {
        $rawData =<<<EOF
     * trusted_domains
     *
     * Your list of trusted domains that users can log into. Specifying trusted
     * domains prevents host header poisoning. Do not remove this, as it performs
     * necessary security checks. Please consider that for backend processes like
     * background jobs or occ commands, the url parameter in key ``overwrite.cli.url``
     * is used. For more details please see that key.
     */
    'trusted_domains' => [
        'demo.example.org',
        'otherdomain.example.org',
    ];
EOF;

        $parsedData = [
            'summary' => 'trusted_domains',
            'description' => 'Your list of trusted domains that users can log into. Specifying trusted
domains prevents host header poisoning. Do not remove this, as it performs
necessary security checks. Please consider that for backend processes like
background jobs or occ commands, the url parameter in key `overwrite.cli.url`
is used. For more details please see that key.',
            'section_header' => false,
            'code' => "'trusted_domains' => [
    'demo.example.org',
    'otherdomain.example.org',
];",

        ];

        return [
          [
              $rawData, $parsedData
          ]
        ];
    }

    /**
     * @dataProvider parseBlockDataProvider
     */
    public function testCanParseBlockCorrectly($rawData, $parsedData)
    {

        $command = new ConvertConfigToAsciiDocFormatCommand();
        $this->assertSame([$parsedData], $command->parseBlock($rawData, []));
    }

    public function testExecute()
    {
        //$this->markTestSkipped();
        $application = new Application();
        $application->add(new ConvertConfigToAsciiDocFormatCommand());

        $outputFile = 'opt/core/config/config_sample_php_parameters.adoc';
        $expectedOutput = file_get_contents(__DIR__ . '/../data/correct-output.adoc');

        $command = $application->find('config:convert-to-asciidoc');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command'  => $command->getName(),
            '--input-file' => vfsStream::url('opt/core/config/config.sample.php'),
            '--output-file' => vfsStream::url($outputFile),
            '--tag' => '0.0.1'
        ]);

        // the output of the command in the console
        $output = $commandTester->getDisplay();

        $this->assertFileExists(vfsStream::url($outputFile));
        $this->assertEquals($expectedOutput, file_get_contents(vfsStream::url($outputFile)));
    }
}
