using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.DependencyInjection;

namespace Botovis;

public static class BotovisServiceExtensions
{
    /// <summary>
    /// Add Botovis services to the DI container.
    /// </summary>
    public static IServiceCollection AddBotovis(
        this IServiceCollection services,
        IConfiguration configuration)
    {
        services.Configure<BotovisOptions>(configuration.GetSection("Botovis"));
        services.AddSingleton<BotovisClient>();
        return services;
    }

    /// <summary>
    /// Add Botovis services with manual configuration.
    /// </summary>
    public static IServiceCollection AddBotovis(
        this IServiceCollection services,
        Action<BotovisOptions> configure)
    {
        services.Configure(configure);
        services.AddSingleton<BotovisClient>();
        return services;
    }
}
