export interface BotovisConfig {
  apiKey?: string;
  env?: 'production' | 'staging' | 'development';
  debug?: boolean;
}

export class Botovis {
  private config: Required<BotovisConfig>;

  constructor(config: BotovisConfig = {}) {
    this.config = {
      apiKey: config.apiKey ?? process.env.BOTOVIS_API_KEY ?? '',
      env: config.env ?? (process.env.BOTOVIS_ENV as BotovisConfig['env']) ?? 'production',
      debug: config.debug ?? process.env.BOTOVIS_DEBUG === 'true' ?? false,
    };
  }

  /**
   * Get a configuration value.
   */
  getConfig<K extends keyof BotovisConfig>(key: K): BotovisConfig[K] {
    return this.config[key];
  }

  /**
   * Get the current Botovis SDK version.
   */
  version(): string {
    return '0.1.0';
  }
}

export default Botovis;
