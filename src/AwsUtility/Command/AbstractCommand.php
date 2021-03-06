<?php

namespace AwsUtility\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AbstractCommand extends Command
{
    /**
     * @var string
     */
    protected $region = '';

    /**
     * @var \Aws\Credentials\CredentialsInterface
     */
    protected $credentials = null;
    
    /**
     * @var \Aws\Credentials\CredentialsInterface
     */
    protected $assumedRoleCredentials = null;
    
    /**
     * @var string
     */
    protected $assumedRoleSessionName = 'aws-utility';

    /**
     * @var \AwsUtility\Settings
     */
    protected $settings = [];

    /**
     * @param \AwsUtility\Settings $settings
     */
    public function setSettings(\AwsUtility\Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws \Exception
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $this->region = $this->settings->get('defaults.region');
        if ($region = $input->getOption('region')) {
            $this->region = $region;
        }

        $awsAccessKeyId = $input->getOption('awsAccessKeyId');
        $awsSecretAccessKey = $input->getOption('awsSecretAccessKey');
        $this->credentials = new \AwsUtility\Security\Credentials($awsAccessKeyId, $awsSecretAccessKey);

        if ($input->hasParameterOption('--assumeRole')) {
            $assumedRoleArn = $input->getOption('assumedRoleArn');
            if (!$assumedRoleArn) {
                // TODO ask for roles with filter http://docs.aws.amazon.com/aws-sdk-php/v3/api/api-iam-2010-05-08.html#listroles
                throw new \Exception('Option --assumedRoleArn empty');
            }

            $stsClient = new \Aws\Sts\StsClient(
                [
                    'endpoint' => $this->settings->get('services.sts.endpoint'),
                    'version' => $this->settings->get('services.sts.version'),
                    'region' => $this->region,
                    'credentials' => $this->credentials
                ]
            );
            $securityTokenService = new \AwsUtility\Security\TokenService($stsClient, $assumedRoleArn, $this->assumedRoleSessionName, $input->getOption('assumedRoleExternalId'));

            $awsAccessKeyId = $securityTokenService->getAwsAccessKeyId();
            $awsSecretAccessKey = $securityTokenService->getAwsSecretAccessKey();
            $token = $securityTokenService->getToken();
            
            $this->assumedRoleCredentials = new \AwsUtility\Security\Credentials($awsAccessKeyId, $awsSecretAccessKey, $token);
        }
    }
    
    protected function configure()
    {
        $this
            ->addOption(
                'region',
                null,
                InputOption::VALUE_OPTIONAL,
                'Region to which the client is configured to send requests'
            )
            ->addOption(
                'awsAccessKeyId',
                null,
                InputOption::VALUE_OPTIONAL,
                'AWS access key'
            )
            ->addOption(
                'awsSecretAccessKey',
                null,
                InputOption::VALUE_OPTIONAL,
                'AWS secret key'
            )
            ->addOption(
                'assumeRole',
                null,
                InputOption::VALUE_OPTIONAL,
                'Enable assume role'
            )
            ->addOption(
                'assumedRoleArn',
                null,
                InputOption::VALUE_OPTIONAL,
                'The Amazon Resource Name (ARN) of the role to assume.'
            )
            ->addOption(
                'assumedRoleExternalId',
                null,
                InputOption::VALUE_OPTIONAL,
                'A unique identifier that is used by third parties when assuming roles in their customers accounts.'
            );
    }    
    
    /**
     * @return \Aws\Credentials\CredentialsInterface
     */
    protected function getCredentials()
    {
        return $this->credentials;
    }
    
    /**
     * @return \Aws\Credentials\CredentialsInterface
     */
    protected function getAssumedRoleCredentials()
    {
        return $this->assumedRoleCredentials;
    }

    /**
     * @return string
     */
    protected function getRegion()
    {
        return $this->region;
    }
}
