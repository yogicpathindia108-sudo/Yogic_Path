export const parseArgs = (argv = process.argv.slice(2)) => {
  const args = [...argv];

  const getArgValue = (name) => {
    const index = args.indexOf(name);
    if (-1 === index) {
      return null;
    }
    return args[index + 1] ?? null;
  };

  const hasArg = (name) => args.includes(name);

  const getArgValues = (name) => {
    const values = [];
    args.forEach((arg, index) => {
      if (arg === name) {
        const value = args[index + 1];
        if (value) {
          values.push(value);
        }
      }
    });
    return values;
  };

  return { args, getArgValue, getArgValues, hasArg };
};
