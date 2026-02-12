using Xunit;

namespace Botovis.Tests;

public class BotovisClientTests
{
    [Fact]
    public void Should_Create_Instance_With_Default_Options()
    {
        var client = new BotovisClient(new BotovisOptions());
        Assert.NotNull(client);
    }

    [Fact]
    public void Should_Return_Version()
    {
        var client = new BotovisClient(new BotovisOptions());
        Assert.Equal("0.1.0", client.Version());
    }

    [Fact]
    public void Should_Return_Config_Value()
    {
        var options = new BotovisOptions { ApiKey = "test-key" };
        var client = new BotovisClient(options);
        Assert.Equal("test-key", client.GetConfig("ApiKey"));
    }

    [Fact]
    public void Should_Return_Null_For_Unknown_Key()
    {
        var client = new BotovisClient(new BotovisOptions());
        Assert.Null(client.GetConfig("UnknownKey"));
    }

    [Fact]
    public void Should_Default_To_Production_Env()
    {
        var client = new BotovisClient(new BotovisOptions());
        Assert.Equal("production", client.GetConfig("Env"));
    }
}
