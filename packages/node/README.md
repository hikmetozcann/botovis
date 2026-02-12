# Botovis for Node.js

Official Botovis SDK for Node.js / TypeScript.

## Requirements

- Node.js 18+

## Installation

```bash
npm install botovis
```

## Usage

```typescript
import { Botovis } from 'botovis';

const botovis = new Botovis({
  apiKey: 'your-api-key',
  env: 'production',
});

console.log(botovis.version()); // 0.1.0
```

## Configuration

You can configure via constructor or environment variables:

```env
BOTOVIS_API_KEY=your-api-key
BOTOVIS_ENV=production
BOTOVIS_DEBUG=false
```

## Testing

```bash
npm test
```

## License

MIT
