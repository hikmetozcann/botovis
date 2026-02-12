using Microsoft.Extensions.Options;

namespace Botovis;

public class BotovisClient
{
    private readonly BotovisOptions _options;

    public BotovisClient(IOptions<BotovisOptions> options)
    {
        _options = options.Value;
    }

    public BotovisClient(BotovisOptions options)
    {
        _options = options;
    }

    /// <summary>
    /// Get a configuration value by key.
    /// </summary>
    public string? GetConfig(string key)
    {
        return key switch
        {
            "ApiKey" => _options.ApiKey,
            "Env" => _options.Env,
            "Debug" => _options.Debug.ToString(),
            _ => null
        };
    }

    /// <summary>
    /// Get the current Botovis SDK version.
    /// </summary>
    public string Version() => "0.1.0";
}
