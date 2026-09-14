const fs = require('fs');
const path = require('path');
const https = require('https');
const http = require('http');
const { URL } = require('url');
const zlib = require('zlib');
const git = require('isomorphic-git');

const rootDir = path.resolve(__dirname, '../..');
const tokenFile = path.join(rootDir, '.git', 'git-token');
const token = process.env.GITHUB_TOKEN || (fs.existsSync(tokenFile) ? fs.readFileSync(tokenFile, 'utf8').trim() : '');
const commitMessage = process.argv.slice(2).join(' ') || 'Update website';

const customHttp = {
  async request({
    onProgress,
    url,
    method = 'GET',
    headers = {},
    agent,
    body,
    signal,
  }) {
    let tempFilePath = null;
    let contentLength = 0;

    if (body) {
      tempFilePath = path.join(__dirname, `temp-body-${Date.now()}.bin`);
      const ws = fs.createWriteStream(tempFilePath);
      for await (const chunk of body) {
        contentLength += chunk.length;
        ws.write(chunk);
      }
      await new Promise((resolve, reject) => {
        ws.on('error', reject);
        ws.end(resolve);
      });
      headers['content-length'] = String(contentLength);
    }

    return new Promise((resolve, reject) => {
      const parsedUrl = new URL(url);
      const isHttps = parsedUrl.protocol === 'https:';
      const client = isHttps ? https : http;

      const reqOptions = {
        protocol: parsedUrl.protocol,
        hostname: parsedUrl.hostname,
        port: parsedUrl.port || (isHttps ? 443 : 80),
        path: parsedUrl.pathname + parsedUrl.search,
        method: method.toUpperCase(),
        headers: headers,
        timeout: 900000,
      };

      const req = client.request(reqOptions, (res) => {
        if (res.statusCode >= 300 && res.statusCode < 400 && res.headers.location) {
          if (tempFilePath && fs.existsSync(tempFilePath)) {
            try { fs.unlinkSync(tempFilePath); } catch (e) {}
          }
          return resolve(customHttp.request({
            onProgress,
            url: res.headers.location,
            method,
            headers,
            agent,
            body: null,
            signal,
          }));
        }

        let handledStream = res;
        const enc = res.headers['content-encoding'];
        if (enc === 'gzip') {
          handledStream = res.pipe(zlib.createGunzip());
        } else if (enc === 'deflate') {
          handledStream = res.pipe(zlib.createInflate());
        }
        res.on('end', () => {
          if (tempFilePath && fs.existsSync(tempFilePath)) {
            try { fs.unlinkSync(tempFilePath); } catch (e) {}
          }
        });

        resolve({
          url: url,
          method: method,
          statusCode: res.statusCode,
          statusMessage: res.statusMessage,
          body: handledStream,
          headers: res.headers,
        });
      });

      req.on('error', (err) => {
        if (tempFilePath && fs.existsSync(tempFilePath)) {
          try { fs.unlinkSync(tempFilePath); } catch (e) {}
        }
        reject(err);
      });

      req.on('timeout', () => {
        req.destroy(new Error('HTTP request timed out'));
      });

      if (tempFilePath) {
        const rs = fs.createReadStream(tempFilePath);
        rs.pipe(req);
      } else {
        req.end();
      }
    });
  }
};

async function sync() {
  console.log('📦 Checking for changes...');
  
  // Status matrix to find changed/untracked files
  const FILE = 0, HEAD = 1, WORKDIR = 2, STAGE = 3;
  const statusMatrix = await git.statusMatrix({
    fs,
    dir: rootDir,
    filter: f => !f.startsWith('node_modules') && !f.startsWith('.next') && !f.startsWith('backend/.venv')
  });

  let stagedCount = 0;
  for (const row of statusMatrix) {
    const [filepath, head, workdir, stage] = row;
    if (workdir === 0 && stage === 1) {
      // deleted
      await git.remove({ fs, dir: rootDir, filepath });
      stagedCount++;
    } else if (workdir === 2 && stage !== 2) {
      // modified or new
      await git.add({ fs, dir: rootDir, filepath });
      stagedCount++;
    }
  }

  if (stagedCount > 0) {
    console.log(`✓ Staged ${stagedCount} changed files.`);
    const sha = await git.commit({
      fs,
      dir: rootDir,
      message: commitMessage,
      author: {
        name: 'Yogic Path',
        email: 'yogicpathindia108@gmail.com'
      }
    });
    console.log(`✓ Committed changes: [${commitMessage}] (${sha.slice(0, 7)})`);
  } else {
    console.log('ℹ No new changes to commit.');
  }

  console.log('🚀 Pushing to GitHub (origin/main)...');
  const pushRes = await git.push({
    fs,
    http: customHttp,
    dir: rootDir,
    remote: 'origin',
    ref: 'main',
    onAuth: () => ({ username: token }),
    onProgress: (evt) => {
      if (evt.total) {
        process.stdout.write(`\r[Upload] ${evt.phase}: ${evt.loaded}/${evt.total}`);
      }
    }
  });

  console.log('\n\n✅ Push to GitHub successful!');
  console.log('🔗 View on GitHub: https://github.com/yogicpathindia108-sudo/Yogic_Path\n');
}

sync().catch(err => {
  console.error('\n❌ Push failed:', err);
  process.exit(1);
});
