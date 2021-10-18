import React from 'react';
import { BrowserRouter as Router, Route, Link, Redirect } from 'react-router-dom';
import Kakuro from './Kakuro';
import Design from './Design';

class App extends React.Component {
    render() {
        return (
          <div className="App">
            <header className="App-header">
              <Router>
                  <div>
                    <Route path="/grid/design/:id" exact render={(props) => {return <Design {...props} />}} />
                    <Route path="/grid/:id" exact render={(props) => {return <Kakuro {...props} />}} />
                    <Route path="/app_dev.php/grid/design/:id" exact render={(props) => {return <Design {...props} />}} />
                    <Route path="/app_dev.php/grid/:id" exact render={(props) => {return <Kakuro {...props} />}} />
                  </div>
              </Router>
            </header>
          </div>
        )
    }
}

export default App;