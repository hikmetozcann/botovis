# Botovis for .NET

Official Botovis SDK for .NET / ASP.NET Core.

## Requirements

- .NET 8.0+

## Installation

```bash
dotnet add package Botovis
```

## Usage

### ASP.NET Core (DI)

```csharp
// Program.cs
builder.Services.AddBotovis(builder.Configuration);
```

```json
// appsettings.json
{
  "Botovis": {
    "ApiKey": "your-api-key",
    "Env": "production",
    "Debug": false
  }
}
```

```csharp
// In your service/controller
public class MyService
{
    private readonly BotovisClient _botovis;

    public MyService(BotovisClient botovis)
    {
        _botovis = botovis;
    }

    public string GetVersion() => _botovis.Version();
}
```

### Manual Configuration

```csharp
builder.Services.AddBotovis(options =>
{
    options.ApiKey = "your-api-key";
    options.Env = "production";
});
```

## Testing

```bash
dotnet test
```

## License

MIT
