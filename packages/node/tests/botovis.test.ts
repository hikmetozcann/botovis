import { describe, it, expect } from 'vitest';
import { Botovis } from '../src/index';

describe('Botovis', () => {
  it('should create an instance with default config', () => {
    const botovis = new Botovis();
    expect(botovis).toBeInstanceOf(Botovis);
  });

  it('should return the version', () => {
    const botovis = new Botovis();
    expect(botovis.version()).toBe('0.1.0');
  });

  it('should accept custom config', () => {
    const botovis = new Botovis({ apiKey: 'test-key', env: 'development' });
    expect(botovis.getConfig('apiKey')).toBe('test-key');
    expect(botovis.getConfig('env')).toBe('development');
  });

  it('should default to production env', () => {
    const botovis = new Botovis();
    expect(botovis.getConfig('env')).toBe('production');
  });

  it('should default debug to false', () => {
    const botovis = new Botovis();
    expect(botovis.getConfig('debug')).toBe(false);
  });
});
